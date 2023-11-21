<?php

class ControllerExtensionPaymentScanpayMobilePay extends Controller {
    // index(): only executed on order confirmation page (?route=checkout/confirm)
    public function index() {
        return $this->load->view('extension/payment/scanpay', [
            'action' => $this->url->link('extension/payment/scanpay/pay', 'scanpay_go=mobilepay', true)
        ]);
    }
}
