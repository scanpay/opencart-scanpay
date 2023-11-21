<?php

class ModelExtensionPaymentScanpayMobilePay extends Model {
    /*
        getMethod() used in checkout to show payment method
        index.php?route=checkout/payment_method
    */
    public function getMethod($address, $total): array {
        return [
            'code'       => 'scanpay_mobilepay',
            'title'      => 'MobilePay',
            'terms'      => '',
            'sort_order' => $this->config->get('payment_scanpay_mobilepay_sort_order'),
        ];
    }
}
