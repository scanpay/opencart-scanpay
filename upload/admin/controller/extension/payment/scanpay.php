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

    /*
        order(): Add payment details to order edit
        admin/index.php?route=sale/order/info
    */
    public function order() {
        $orderid = $this->request->get['order_id'];
        $order = $this->model_sale_order->getOrder($orderid);
        $apikey = (string)$this->config->get('payment_scanpay_apikey');
        $shopid = (int)explode(':', $apikey)[0];

        require DIR_SYSTEM . 'library/scanpay/math.php';
        require DIR_SYSTEM . 'library/scanpay/db.php';
        $sdb = new ScanpayDb($this->db, $shopid);
        $data = $sdb->getMeta($orderid);

        if (isset($data['trnid'])) {
            $this->document->addStyle('view/stylesheet/scanpay/order.css?v1');
            $this->document->addScript('view/javascript/scanpay/order.js?v5');
            $this->document->setTitle('#' . $orderid . ' - ' . $order['firstname'] .
                ' ' . $order['lastname']);
            $data['user_token'] = $this->session->data['user_token'];
            $data['currency'] = explode(' ', $data['authorized'])[1];
            $authorized = explode(' ', $data['authorized'])[0];
            $captured = explode(' ', $data['captured'])[0];
            $refunded = explode(' ', $data['refunded'])[0];
            $net = scanpay_submoney($captured, $refunded);
            $data['net_payment'] = $net . ' ' . $data['currency'];
            $data['net_payment_pct'] = round(($net / $authorized) * 100, 2);
            return $this->load->view('extension/payment/scanpay_order', $data);
        }
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
        $shopid = (int)$this->request->get['shopid'];
        $sdb = new ScanpayDb($this->db, $shopid);
        $orderid = (int)$this->request->get['orderid'];
        $data = $sdb->getMeta($orderid);
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
        require DIR_SYSTEM . 'library/scanpay/db.php';
        $sdb = new ScanpayDb($this->db, 0);
        $sdb->createTables();
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_seq");
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_order");
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('scanpay');
    }
}
