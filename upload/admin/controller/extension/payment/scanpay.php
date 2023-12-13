<?php

class ControllerExtensionPaymentScanpay extends Controller {
    // index(): only executed in plugin settings
    public function index() {
        $this->document->setTitle('Scanpay');
        $this->document->addStyle('view/stylesheet/scanpay/settings.css?v01');
        $this->document->addScript('view/javascript/scanpay/settings.js?v01');
        $this->load->model('setting/setting');

        $catalog = ($this->request->server['HTTPS']) ? HTTPS_CATALOG : HTTP_CATALOG;
        $token = $this->session->data['user_token'];
        $data = [
            'header' => $this->load->controller('common/header'),
            'column_left' => $this->load->controller('common/column_left'),
            'footer' => $this->load->controller('common/footer'),
            'logsurl' => $this->url->link('tool/log', "user_token=$token"),
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
        $settings = [
            'payment_scanpay_status' => 0,
            'payment_scanpay_language' => 'auto',
            'payment_scanpay_apikey' => '',
            'payment_scanpay_auto_capture' => 5,
            'payment_scanpay_sort_order' => 0
        ];
        foreach ($settings as $x => &$default) {
            $data[$x] = $this->request->post[$x] ?? $this->config->get($x) ?: $default;
        }

        if (preg_match("/\d+:\S+/", $data['payment_scanpay_apikey'])) {
            require DIR_SYSTEM . 'library/scanpay/db.php';
            $shopid = (int)explode(':', $data['payment_scanpay_apikey'])[0];
            $sdb = new ScanpayDb($this->db, $shopid);
            $data['shopid'] = $shopid;
            $data['mtime'] = $sdb->getSeq()['mtime'];
            $data['dtime'] = time() - $data['mtime'];
            $data['pingdate'] = date("Y-m-d H:i", $data['mtime']);
            $data['pingurl'] = "https://dashboard.scanpay.dk/$shopid/settings/api/setup?module=opencart&url=" .
                rawurlencode($catalog . 'index.php?route=extension/payment/scanpay/ping');
        } elseif ($data['payment_scanpay_apikey'] !== '') {
            $data['invalid_apikey'] = true;
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

    // Add payment details to order info (route=sale/order/info)
    public function order() {
        $this->document->addStyle('view/stylesheet/scanpay/order.css?v1');
        $this->document->addScript('view/javascript/scanpay/order.js?v5');
        $this->document->setTitle('#' . (int)$this->request->get['order_id']);
        return $this->load->view('extension/payment/scanpay_order');
    }

    public function ajaxSeqMtime() {
        require DIR_SYSTEM . 'library/scanpay/db.php';
        $shopid = (int)$this->request->get['shopid'];
        $sdb = new ScanpayDb($this->db, $shopid);
        $res = $sdb->getSeq()['mtime'];
        $this->response->setOutput($res);
    }

    public function ajaxScanpayOrder() {
        require DIR_SYSTEM . 'library/scanpay/db.php';
        $apikey = (string)$this->config->get('payment_scanpay_apikey');
        $sdb = new ScanpayDb($this->db, (int)explode(':', $apikey)[0]);
        $data = $sdb->getMeta((int)$this->request->get['orderid']);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    public function install() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('scanpay');
        $this->model_setting_event->addEvent(
            'scanpay',
            'catalog/model/checkout/order/addOrderHistory/after',
            'extension/payment/scanpay/captureOnOrderStatus'
        );

        // Delete old tables if they exist
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_seq");
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_order");

        // Create new tables
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "scanpay_seq (
                shopid  INT unsigned NOT NULL UNIQUE,
                seq     INT unsigned NOT NULL,
                ping    INT unsigned NOT NULL,
                mtime   BIGINT unsigned NOT NULL,
                PRIMARY KEY (shopid)
            ) CHARSET=latin1;"
        );
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "scanpay_order (
                orderid BIGINT unsigned NOT NULL UNIQUE,
                shopid INT unsigned NOT NULL,
                trnid INT unsigned NOT NULL,
                rev INT unsigned NOT NULL,
                nacts INT unsigned NOT NULL,
                authorized VARCHAR(64) NOT NULL,
                captured VARCHAR(64) NOT NULL,
                refunded VARCHAR(64) NOT NULL,
                voided VARCHAR(64) NOT NULL,
                PRIMARY KEY (orderid)
            ) CHARSET = latin1;"
        );
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_seq");
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_order");
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('scanpay');
    }
}
