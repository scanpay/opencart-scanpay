<?php

class ControllerExtensionPaymentScanpayMobilePay extends Controller {
    // index(): only executed on order confirmation page (?route=checkout/confirm)
    public function index() {
        $data['action'] = $this->url->link('extension/payment/scanpay_mobilepay/pay', '', true);
        return $this->load->view('extension/payment/scanpay', $data);
    }

    // pay() is called on form submit ($data['action'])
    public function pay() {
        $this->load->model('extension/payment/scanpay');
        $this->response->redirect(
            $this->model_extension_payment_scanpay->newUrl('mobilepay'),
            302
        );
    }
}
