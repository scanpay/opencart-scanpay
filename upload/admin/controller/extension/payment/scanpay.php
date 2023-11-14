<?php

class ControllerExtensionPaymentScanpay extends Controller {
    /*
        index(): only executed in plugin settings
        admin/index.php?route=extension/payment/scanpay
    */
    public function index() {
        $this->document->setTitle('Scanpay');
        $this->document->addStyle('view/stylesheet/scanpay/settings.css?v01');
        $this->document->addScript('view/javascript/scanpay/settings.js?v01');
        $this->load->model('setting/setting');

        require DIR_SYSTEM . 'library/scanpay/db.php';
        $apikey = (string)($this->request->post['payment_scanpay_apikey'] ??
            $this->config->get('payment_scanpay_apikey'));
        $shopid = (int)explode(':', $apikey)[0];
        if ($shopid > 0) {
            $mtime = (getScanpaySeq($this->db, $shopid))['mtime'];
        } else {
            $mtime = 0;
        }

        $catalog = ($this->request->server['HTTPS']) ? HTTPS_CATALOG : HTTP_CATALOG;
        $token = $this->session->data['user_token'];
        $data = [
            'header' => $this->load->controller('common/header'),
            'column_left' => $this->load->controller('common/column_left'),
            'footer' => $this->load->controller('common/footer'),
            'shopid' => $shopid,
            'logsurl' => $this->url->link('tool/log', "user_token=$token"),
            'pingurl' => 'https://dashboard.scanpay.dk/' . $shopid . '/settings/api/setup?module=opencart&url=' .
                rawurlencode($catalog . 'index.php?route=extension/payment/scanpay/ping'),
            'dtime' => time() - $mtime,
            'pingdate' => date("Y-m-d H:i", $mtime),
            'action' => $this->url->link('extension/payment/scanpay', "user_token=$token"),
            'cancel' => $this->url->link('marketplace/extension', "user_token=$token&type=payment"),
            'breadcrumbs' => [
                [
                    'text' => $this->language->get('text_home'),
                    'href' => $this->url->link('common/dashboard', "user_token=$token", true)
                ],
                [
                    'text' => $this->language->get('text_extension'),
                    'href' => $this->url->link('marketplace/extension', "user_token=$token&type=payment", true)
                ],
                [
                    'text' => 'Scanpay',
                    'href' => $this->url->link('extension/payment/scanpay', "user_token=$token", true)
                ]
            ]
        ];
        $settings = ['status', 'language', 'apikey', 'auto_capture', 'sort_order'];
        foreach ($settings as $x) {
            $key = 'payment_scanpay_' . $x;
            $data[$key] = $this->request->post[$key] ?? $this->config->get($key);
        }

        // Validate API key
        if ($data['payment_scanpay_apikey'] !== '') {
            $data['invalid_apikey'] = !preg_match("/\d+:\S+/", $data['payment_scanpay_apikey']);
        }

        // Handle save button
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if ($this->user->hasPermission('modify', 'extension/payment/scanpay')) {
                $this->model_setting_setting->editSetting('payment_scanpay', $this->request->post);
                $data['success_msg'] = 'Success: You have successfully modified your Scanpay settings!';
            } else {
                $data['error_warning'] = 'Warning: You do not have permission to modify these settings!';
            }
        }
        $this->response->setOutput($this->load->view('extension/payment/scanpay', $data));
    }

    /*
        order(): Add payment details to order edit
        admin/index.php?route=sale/order/info
    */
    public function order() {
        $this->document->addStyle('view/stylesheet/scanpay/order.css?v1');
        $this->document->addScript('view/javascript/scanpay/order.js?v1');
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

    public function ajaxSeqMtime() {
        $shopid = (int)$this->request->get['shopid'];
        require DIR_SYSTEM . 'library/scanpay/db.php';
        echo (getScanpaySeq($this->db, $shopid))['mtime'];
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
