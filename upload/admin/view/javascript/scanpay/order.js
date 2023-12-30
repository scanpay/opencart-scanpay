
(() => {
    const version = 'EXTENSION_VERSION'; // Inserted by build script
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('user_token');
    const orderid = urlParams.get('order_id');
    const ajaxMetaUrl = 'index.php?route=extension/payment/scanpay/ajaxMeta&user_token=' +
        token + '&orderid=' + urlParams.get('order_id');

    let status;
    let row;

    function request(url, ttl) {
        const reqCache = JSON.parse(localStorage.getItem('scanpay_cache')) || {};
        const now = Math.floor(Date.now() / 1000);
        const key = url.replace('user_token=' + token, ''); // TODO: find a more beautiful way
        if (ttl && reqCache[key] && now < reqCache[key].next) {
            return new Promise(resolve => resolve(reqCache[key].result));
        }
        return fetch(url)
            .then((res) => res.json())
            .then((o) => {
                reqCache[key] = { result: o, next: now + ttl }
                localStorage.setItem('scanpay_cache', JSON.stringify(reqCache));
                return o;
            });
    }

    function calcNetPayment(o) {
        // Refund and Void are mutually exclusive operations
        if (o.refunded === o.voided) return o.captured.split(' '); // No refunds or Voids
        if (o.voided === o.authorized) return o.captured.split(' '); // Voided
        if (o.refunded === o.authorized) return o.voided.split(' '); // Fully refunded
        const c = o.captured.split(' ');
        return [parseFloat(c[0]) - parseFloat(o.refunded.split(' ')[0]), c[1]];
    }

    function showWarning(msg) {
        const box = document.createElement('div');
        box.className = 'alert alert-danger';
        box.innerHTML = '<i class="fa fa-exclamation-circle"></i> <b>Warning</b>: ' + msg;
        document.getElementById('scanpay-warnings').appendChild(box);
    }

    function updateOptsRow(o) {
        const dash = 'https://dashboard.scanpay.dk/' + o.shopid + '/' + o.trnid;
        const net = calcNetPayment(o);
        row.children[1].textContent = net[0] + ' ' + net[1];
        if (o.voided === o.authorized) {
            row.children[2].innerHTML = `<button disabled class="btn btn-warning btn-xs" title="Voided"><i class="fa fa-ban"></i></button>`;
        } else if (o.captured === o.authorized) {
            if (o.refunded === o.captured) {
                row.children[2].innerHTML = `<button disabled class="btn btn-danger btn-xs"><i class="fa fa-minus-circle"></i></button>`;
            } else {
                row.children[2].innerHTML = `<a href="${dash}/refund" target="_blank" class="btn btn-danger btn-xs"><i class="fa fa-minus-circle"></i></a>`;
            }
        } else {
            row.children[2].innerHTML = `<a href="${dash}/capture" target="_blank" class="btn btn-success btn-xs"><i class="fa fa-plus-circle"></i></a>`;
        }
    }

    function buildTable(o) {
        const target = document.getElementById('scanpay--order--tbody');
        const tbody = target.cloneNode(false);
        const data = [
            ['Authorized', o.authorized],
            ['Captured', o.captured],
            ['Refunded', o.refunded],
            ['Voided', o.voided]
        ];
        for (const x of data) {
            const row = tbody.insertRow();
            const cell1 = row.insertCell();
            cell1.textContent = x[0];
            const cell2 = row.insertCell();
            cell2.textContent = x[1];
        }
        target.replaceWith(tbody);
    }

    // Check if the extension is up-to-date (cache result for 10 minutes)
    function checkExtensionVersion() {
        request('https://api.github.com/repos/scanpay/opencart-scanpay/releases/latest', 600)
            .then((o) => {
                const release = o.tag_name.substring(1);
                if (release !== version) {
                    showWarning(
                        `Your scanpay extension <i>(${version})</i> is <b class="scanpay-outdated">outdated</b>.
                        Please update to ${release} (<a href="//github.com/scanpay/opencart-scanpay/releases"
                        target="_blank">changelog</a>)`
                    );
                }
            });
    }

    function checkPingStatus() {
        request('index.php?route=extension/payment/scanpay/ajaxSeqMtime&user_token=' + token, 120)
            .then((o) => {
                const dmins = Math.floor((Math.floor(Date.now() / 1000) - o.mtime) / 60);
                if (o.mtime === 0 || dmins < 10) return;
                let ts = dmins + ' minutes';
                if (dmins > 120) ts = Math.floor(dmins / 60) + ' hours'
                showWarning('Your scanpay extension is out of sync: ' + ts + ' since last synchronization.');
            });
    }

    function checkMismatch(o) {
        if (status === '5') {
            if (o.captured === o.voided) {
                showWarning('The order is completed, but the payment has not been captured');
            } else if (o.voided === o.authorized) {
                showWarning('The payment is voided, but the order is marked as completed');
            } else if (o.refunded === o.authorized) {
                showWarning('The payment is fully refunded, but the order is marked as "completed"');
            }
        }
    }


    let rev = 0;
    function build(meta) {
        if (!meta.trnid || meta.rev === rev) return;

        updateOptsRow(meta);
        buildTable(meta);
        checkMismatch(meta);

        if (!rev) {
            // OpenCart JS is true BS. We put our checks here to avoid their actions.
            checkExtensionVersion();
            checkPingStatus();

            // Add static links to the transaction
            const dash = 'https://dashboard.scanpay.dk/' + meta.shopid + '/' + meta.trnid;
            const h4 = document.getElementById('scanpay-h4');
            h4.textContent = '#' + meta.trnid;
            h4.href = dash;
            document.getElementById('scanpay-capture-btn').href = dash + '/capture';
            document.getElementById('scanpay-refund-btn').href = dash + '/refund';
        }
        rev = meta.rev;
    }

    function domReady() {
        status = document.getElementById('input-order-status').value;
        document.querySelector('h1').innerHTML = '#' + orderid; // replace 'Scanpay' with orderid

        // Insert Options row with loading spinner
        row = document.querySelectorAll('.table')[2].insertRow();
        row.innerHTML = '<td>Net payment</td><td class="text-right"></td><td class="text-center"></td>';

        const elem = document.createElement('div');
        elem.id = 'scanpay-warnings';
        document.querySelector('#content > .container-fluid').prepend(elem);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", domReady);
    } else {
        domReady();
    }

    // We have to use onload because of OC :/
    window.addEventListener("load", () => {
        fetch(ajaxMetaUrl)
            .then((r) => r.json())
            .then(build);

        let controller;
        document.addEventListener("visibilitychange", () => {
            // Check for rev updates when user comes back from dashboard
            if (document.visibilityState == "visible") {
                controller = new AbortController();
                fetch(ajaxMetaUrl + '&rev=' + rev, { signal: controller.signal })
                    .then((r) => r.json())
                    .then(build)
                    .catch((e) => console.log(e));
            } else if (controller) {
                controller.abort();
            }
        });
    });
})();
