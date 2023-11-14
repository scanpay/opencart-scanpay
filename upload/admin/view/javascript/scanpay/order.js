


function init() {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('user_token');

    // Check if API key is set
    const shopid = apikeyField.dataset.shopid;
    if (apikey === '' || shopid === '0') return;

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

