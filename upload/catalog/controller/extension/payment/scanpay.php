<?php

require_once('abstract-scanpay.php');

class ControllerExtensionPaymentScanpay extends AbstractControllerExtensionPaymentScanpay {

    protected function getName() {
        return 'scanpay';
    }

}