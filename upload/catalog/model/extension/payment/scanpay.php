<?php

class ModelExtensionPaymentScanpay extends Model {
    /*
        getMethod() used in checkout to show payment method
        index.php?route=checkout/payment_method
    */
    public function getMethod($address, $total): array {
        $this->language->load('extension/payment/scanpay');
        return [
            'code'       => 'scanpay',
            'title'      => $this->language->get('text_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_scanpay_sort_order'),
        ];
    }
}
