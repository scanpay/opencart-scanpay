<?php

require_once('abstract-scanpay.php');

class ControllerExtensionPaymentScanpay extends AbstractControllerExtensionPaymentScanpay {

    public function index() {
        $this->load->model('extension/payment/scanpay');
        $data['pingurl'] = HTTPS_CATALOG . 'index.php?route=extension/payment/scanpay/ping';
        $shopIdStr = explode(':', $this->config->get('payment_scanpay_apikey'))[0];
        if (ctype_digit($shopIdStr) && (string)(int)$shopIdStr == $shopIdStr) {
            $seqObj = $this->model_extension_payment_scanpay->loadSeq((int)$shopIdStr);
            $mtime = $seqObj['mtime'];
        } else {
            $mtime = 0;
        }
        $data['pingdt'] = $this->fmtdt(time() - $mtime);
        $data['pingstatus'] = $this->pingstatus($mtime);

        $this->_index('', ['payment_scanpay_status', 'payment_scanpay_language', 'payment_scanpay_apikey', 'payment_scanpay_autocapture', 'payment_scanpay_sort_order'], $data);
    }

    public function install() {
        $this->load->model('extension/payment/scanpay');
        $this->model_extension_payment_scanpay->install();
    }

    protected function fmtdt($dt)
    {
        $minute = 60;
        $hour = $minute * 60;
        $day = $hour * 24;
        if ($dt <= 1) {
            return '1 second ago';
        } else if ($dt < $minute) {
            return (string)$dt . ' seconds ago';
        } else if ($dt < $minute + 30) {
            return '1 minute ago';
        } else if ($dt < $hour) {
            return (string)round((float)$dt / $minute) . ' minutes ago';
        } else if ($dt < $hour + 30 * $minute) {
            return '1 hour ago';
        } else if ($dt < $day){
            return (string)round((float)$dt / $hour) . ' hours ago';
        } else if ($dt < $day + 12 * $hour) {
            return '1 day ago';
        } else {
            return (string)round((float)$dt / $day) . ' days ago';
        }
    }

    function pingstatus($mtime) {
        $t = time();
        if ($mtime > $t) {
            $this->log->write('last modified time is in the future');
            return;
        }

        $status = '';
        if ($t < $mtime + 900) {
            return 'ok';
        } else if ($t < $mtime + 3600) {
            return 'warning';
        } else if ($mtime > 0) {
            return 'error';
        } else {
            return 'never--pinged';
        }
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/scanpay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        if (!$this->request->post['payment_scanpay_apikey']) {
            $this->error['payment_scanpay_apikey'] = $this->language->get('error_apikey');
        }
        return !$this->error;
    }
}
