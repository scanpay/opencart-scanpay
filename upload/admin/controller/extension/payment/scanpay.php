<?php

class ControllerExtensionPaymentScanpay extends Controller {
    /*
        index(): only executed in plugin settings
        admin/index.php?route=extension/payment/scanpay
    */
    public function index() {
        $this->language->load('extension/payment/scanpay');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        require DIR_SYSTEM . 'library/scanpay/db.php';

        $apikey = (string)$this->config->get('payment_scanpay_apikey');
        $shopid = (int)explode(':', $apikey)[0];
        if ($shopid > 0) {
            $mtime = (getScanpaySeq($this->db, $shopid))['mtime'];
        } else {
            $mtime = 0;
        }

        $token = $this->session->data['user_token'];
        $data = [
            'header' => $this->load->controller('common/header'),
            'column_left' => $this->load->controller('common/column_left'),
            'footer' => $this->load->controller('common/footer'),
            'pingurl' => HTTPS_CATALOG . 'index.php?route=extension/payment/scanpay/ping',
            'pingdt' => $this->fmtdt(time() - $mtime),
            'pingstatus' => $this->pingstatus($mtime),
            'action' => $this->url->link('extension/payment/scanpay', "user_token=$token", true),
            'cancel' => $this->url->link('marketplace/extension', "user_token=$token&type=payment", true),
        ];
        $settings = ['status', 'language', 'apikey', 'auto_capture', 'sort_order'];
        foreach ($settings as $x) {
            $key = 'payment_scanpay_' . $x;
            $data[$key] = $this->request->post[$key] ?? $this->config->get($key);
        }

        // Handle save button
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if ($this->user->hasPermission('modify', 'extension/payment/scanpay')) {
                $this->model_setting_setting->editSetting('payment_scanpay', $this->request->post);
                $data['success_msg'] = $this->language->get('text_success');
            } else {
                $data['error_warning'] = $this->language->get('error_permission');
            }
        }
        $this->response->setOutput($this->load->view('extension/payment/scanpay', $data));
    }

    protected function fmtdt(int $secs) {
        if ($secs < 600) {
            return $secs . ' seconds ago';
        } elseif ($secs < 7200) {
            return floor($secs / 60) . ' minutes ago';
        } elseif ($secs < 172800) {
            return floor($secs / 3600) . ' hours ago';
        } else {
            return floor($secs / 86400) . ' days ago';
        }
    }

    protected function pingstatus(int $mtime) {
        $t = time();
        if ($t < $mtime + 900) {
            return 'ok';
        } elseif ($t < $mtime + 3600) {
            return 'warning';
        } elseif ($mtime > $t) {
            return 'error';
        } else {
            return 'never--pinged';
        }
    }

    /*
        order(): Add payment details to order edit
        admin/index.php?route=sale/order/info
    */
    public function order() {
        $orderid = $this->request->get['order_id'];
        $apikey = $this->config->get('payment_scanpay_apikey');
        $shopid = (int)explode(':', $apikey)[0];
        require DIR_SYSTEM . 'library/scanpay/db.php';
        $data = getScanpayOrder($this->db, $orderid, $shopid);
        $data['user_token'] = $this->session->data['user_token'];
        if (isset($data['trnid'])) {
            return $this->load->view('extension/payment/scanpay_order', $data);
        }
    }

    public function getPaymentTransaction() {
        echo 'yay';
    }

    public function install() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('scanpay');
        $this->model_setting_event->addEvent(
            'scanpay',
            'catalog/model/checkout/order/addOrderHistory/after',
            'extension/payment/scanpay/captureOnOrderStatus'
        );
        require DIR_SYSTEM . 'library/scanpay/db.php';
        createScanpayTables($this->db);
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_seq");
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_order");
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('scanpay');
    }
}
