<?php

require_once('abstract-scanpay.php');

class ControllerExtensionPaymentScanpayMobilepay extends AbstractControllerExtensionPaymentScanpay {

    protected function getName() {
        return 'scanpay_mobilepay';
    }

    public function index() {
        $this->_index('mobilepay', ['payment_scanpay_mobilepay_status', 'payment_scanpay_mobilepay_sort_order']);
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/scanpay_mobilepay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}
