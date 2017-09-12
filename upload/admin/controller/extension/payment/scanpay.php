<?php
class ControllerExtensionPaymentScanpay extends Controller {
    private $error = array();

    public function index() {
        $this->language->load('extension/payment/scanpay');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/scanpay');
        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
            if (isset($this->request->post['pingurl'])) {
                unset($this->request->post['pingurl']);
            }
            $this->model_setting_setting->editSetting('payment_scanpay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token']  . '&type=payment', true));
        }
        $data['action'] = $this->url->link('extension/payment/scanpay', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $data = $this->fillconfigdata($data, ['payment_scanpay_status', 'payment_scanpay_language', 'payment_scanpay_apikey', 'payment_scanpay_autocapture', 'payment_scanpay_sort_order']);
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

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/payment/scanpay', $data));
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

    protected function fillconfigdata($data, $arr) {
        foreach ($arr as $v) {
            if (isset($this->request->post[$v])) {
                $data[$v] = $this->request->post[$v];
            } else {
                $data[$v] = $this->config->get($v);
            }
        }
        return $data;
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
