<?php

class ModelExtensionPaymentScanpayApplePay extends Model {
    /*
        getMethod() used in checkout to show payment method
        index.php?route=checkout/payment_method
    */
    public function getMethod($address, $total): array {
        return [
            'code'       => 'scanpay_applepay',
            'title'      => 'Apple Pay',
            'terms'      => '',
            'sort_order' => $this->config->get('payment_scanpay_applepay_sort_order'),
        ];
    }
}
