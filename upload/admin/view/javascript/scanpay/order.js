
(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('user_token');
    const orderid = urlParams.get('order_id');
    let rev = 0;

    function calcNetPayment(o) {
        // Refund and Void are mutually exclusive operations
        if (o.refunded === o.voided) return o.captured.split(' '); // No refunds or Voids
        if (o.voided === o.authorized) return o.captured.split(' '); // Voided
        if (o.refunded === o.authorized) return o.voided.split(' '); // Fully refunded

        // Partially refunded
        const c = o.captured.split(' ');
        return [parseFloat(c[0]) - parseFloat(o.refunded.split(' ')[0]), c[1]];
    }

    function getOrderMeta(row) {
        fetch('index.php?route=extension/payment/scanpay/ajaxScanpayOrder&user_token=' + token + '&orderid=' + orderid)
            .then((res) => res.json())
            .then((o) => {
                if (!o.trnid) return;
                const dash = 'https://dashboard.scanpay.dk/' + o.shopid + '/' + o.trnid;
                const net = calcNetPayment(o);
                row.children[1].textContent = net[0] + ' ' + net[1];
                if (o.captured === o.authorized) {
                    if (o.refunded === o.captured) {
                        row.children[2].innerHTML = `<button disabled class="btn btn-danger btn-xs"><i class="fa fa-minus-circle"></i></button>`;
                    } else {
                        row.children[2].innerHTML = `<a href="${dash}/refund" target="_blank" class="btn btn-danger btn-xs"><i class="fa fa-minus-circle"></i></a>`;
                    }
                } else {
                    row.children[2].innerHTML = `<a href="${dash}/capture" target="_blank" class="btn btn-success btn-xs"><i class="fa fa-plus-circle"></i></a>`;
                }

                // Build table
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

                // Table links and buttons
                const h4 = document.getElementById('scanpay-h4');
                h4.textContent = '#' + o.trnid;
                h4.href = dash;
                document.getElementById('scanpay-capture-btn').href = dash + '/capture';
                document.getElementById('scanpay-refund-btn').href = dash + '/refund';

                rev = o.rev;
            });
    }

    function init() {
        // Change the <h1> title from 'Scanpay' to '#orderid'
        document.querySelector('h1').innerHTML = '#' + orderid;

        // Insert Options row with loading spinner
        const table = document.querySelectorAll('.table')[2];
        const row = table.insertRow();
        row.innerHTML = '<td>Net payment</td><td class="text-right"></td><td class="text-center"></td>';
        getOrderMeta(row);

        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState !== "visible") return;

            // Add spinner to panel
            row.children[2].innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

            // Wait 500 ms
            setTimeout(() => {
                getOrderMeta(row);
            }, 500);
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
