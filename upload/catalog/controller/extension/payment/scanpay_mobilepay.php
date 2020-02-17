<?php

require_once('abstract-scanpay.php');

class ControllerExtensionPaymentScanpayMobilepay extends AbstractControllerExtensionPaymentScanpay {
    protected function getName() {
        return 'scanpay_mobilepay';
    }
}
