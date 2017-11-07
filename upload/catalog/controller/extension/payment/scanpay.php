<?php

require_once('abstract-scanpay.php');

class ControllerExtensionPaymentScanpay extends AbstractControllerExtensionPaymentScanpay {

	public function index() {
		return $this->_index();
	}

    public function pay() {
    	$this->mkpayment();
    }
}