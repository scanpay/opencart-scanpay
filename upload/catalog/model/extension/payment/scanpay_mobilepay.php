<?php

class ModelExtensionPaymentScanpayMobilepay extends Model {
    public function getMethod($address, $total) {
        $this->language->load('extension/payment/scanpay_mobilepay');
        return [
            'code'       => 'scanpay_mobilepay',
            'title'      => $this->language->get('text_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_scanpay_mobilepay_sort_order'),
        ];
    }
}
