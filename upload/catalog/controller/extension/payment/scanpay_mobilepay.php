<?php

require_once('abstract-scanpay.php');

class ControllerExtensionPaymentScanpayMobilepay extends AbstractControllerExtensionPaymentScanpay {

	public function index() {
		return $this->_index('mobilepay');
	}

    public function pay() {
    	$this->mkpayment('mobilepay');
    }
}
