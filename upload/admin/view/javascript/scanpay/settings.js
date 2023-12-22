
(() => {
    const version = 'EXTENSION_VERSION'; // Inserted by build script

    function request(url, ttl) {
        const reqCache = JSON.parse(localStorage.getItem('scanpay_cache')) || {};
        const now = Math.floor(Date.now() / 1000);
        if (ttl && reqCache[url] && now < reqCache[url].next) {
            return new Promise(resolve => resolve(reqCache[url].result));
        }
        return fetch(url)
            .then((res) => res.json())
            .then((o) => {
                reqCache[url] = {
                    result: o,
                    next: now + ttl
                }
                localStorage.setItem('scanpay_cache', JSON.stringify(reqCache));
                return o;
            });
    }

    function init() {
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('user_token');

        // Check if the extension is up-to-date (cache result for 10 minutes)
        request('https://api.github.com/repos/scanpay/opencart-scanpay/releases/latest', 600)
            .then((o) => {
                const release = o.tag_name.substring(1);
                const elem = document.getElementById('scanpay--info--version');
                if (release === version) {
                    elem.innerHTML = `The extension is up-to-date (<i>${release},
                        <a href="${o.html_url}" target="_blank">changelog</a></i>)`;
                } else {
                    elem.innerHTML = `The extension <i>(${version})</i> is <b class="scanpay-outdated">outdated</b>.
                        Please update to ${release} (<a href="//github.com/scanpay/opencart-scanpay/releases" target="_blank">changelog</a>)`;
                }
            });

        // Check if API key is set
        if (document.getElementById('input-apikey').value === '') return;

        // Fetch and update ping dtime
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState !== "visible") return;
            fetch('index.php?route=extension/payment/scanpay/ajaxSeqMtime&user_token=' + token)
                .then((res) => res.json())
                .then((o) => {
                    const dtime = Math.floor(Date.now() / 1000) -  parseInt(o.mtime, 10);
                    if (dtime > 600) return;
                    document.getElementById('scanpay--dtime').textContent = dtime;
                    const alert = document.querySelector('.scanpay--ping--alert');
                    if (alert) {
                        alert.remove();
                        document.querySelector('.scanpay--ping--info').classList.remove('scanpay-hide');
                    }
                });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
