


function init() {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('user_token');
    const orderid = urlParams.get('order_id');
    const btn = document.getElementById('scanpay--capture--btn');
    const shopid = parseInt(btn.dataset.shopid, 10);
    const rev = parseInt(btn.dataset.rev, 10);
    if (!shopid || !orderid) return;

    document.querySelector('h1').innerHTML = '#' + orderid; // + `: <span class="scanpay--title">paid</span>`;

    // Fetch and update ping dtime
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState !== "visible") return;

        // Add spinner to panel

        // Fetch
        fetch('index.php?route=extension/payment/scanpay/ajaxScanpayOrder&user_token=' + token + '&shopid=' + shopid + '&orderid=' + orderid)
            .then((res) => res.text())
            .then((str) => {
                console.log(str);
            });
    });


    /*
        $.ajax({
            url: 'index.php?route=extension/payment/scanpay/getPaymentTransaction&user_token={{ user_token }}',
            dataType: 'html',
            data: {
                order_id: '{{ order_id }}'
            },
            beforeSend: function () {
                $('#scanpay-info').html('<i class="fa fa-spinner fa-spin fa-5x" style="text-align: center; margin: 0 auto; width: 100%; font-size: 5em;"></i>');
            },
            success: function (html) {
                $('#scanpay-info').html(html);
            }
        });

    */
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
