
(() => {
    function init() {
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('user_token');

        // Check if API key is set
        const apikeyField = document.getElementById('input-apikey');
        const apikey = apikeyField.value;
        const shopid = apikeyField.dataset.shopid;
        if (apikey === '' || shopid === '0') return;

        // Fetch and update ping dtime
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState !== "visible") return;
            fetch('index.php?route=extension/payment/scanpay/ajaxSeqMtime&user_token=' + token + '&shopid=' + shopid)
                .then((res) => res.text())
                .then((str) => {
                    const dtime = Math.floor(Date.now() / 1000) -  parseInt(str, 10);
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
