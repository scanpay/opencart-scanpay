<?php

class ControllerExtensionPaymentScanpay extends Controller {
    private $seqTbl = DB_PREFIX . 'scanpay_seq';
    private $metaTbl = DB_PREFIX . 'scanpay_order';

    // index(): only executed in plugin settings
    public function index() {
        $this->document->setTitle('Scanpay');
        $this->document->addStyle('view/stylesheet/scanpay/settings.css?vEXTENSION_VERSION');
        $this->document->addScript('view/javascript/scanpay/settings.js?vEXTENSION_VERSION');
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
            $shopid = (int)explode(':', $data['payment_scanpay_apikey'])[0];
            $sql = $this->db->query("SELECT mtime FROM $this->seqTbl WHERE shopid = $shopid");
            if (!$sql->num_rows) {
                $this->db->query("INSERT INTO $this->seqTbl (shopid, seq, ping, mtime) VALUES ($shopid, 0, 0, 0)");
            }
            $mtime = $sql->row['mtime'] ?? 0;
            $data['shopid'] = $shopid;
            $data['dtime'] = time() - $mtime;
            $data['pingdate'] = date("Y-m-d H:i", $mtime);
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
        $this->response->setOutput($this->load->view('extension/payment/scanpay/settings', $data));
    }

    // Add payment details to order info (route=sale/order/info)
    public function order() {
        $this->document->addStyle('view/stylesheet/scanpay/order.css?vEXTENSION_VERSION');
        $this->document->addScript('view/javascript/scanpay/order.js?vEXTENSION_VERSION');
        $this->document->setTitle('#' . (int)$this->request->get['order_id']);
        return $this->load->view('extension/payment/scanpay/order_meta');
    }

    public function ajaxSeqMtime() {
        $shopid = (int)explode(':', (string)$this->config->get('payment_scanpay_apikey'))[0];
        $this->response->setOutput(json_encode(
            $this->db->query("SELECT mtime FROM $this->seqTbl WHERE shopid = $shopid")->row
        ));
    }

    public function ajaxMeta() {
        $shopid = (int)explode(':', (string)$this->config->get('payment_scanpay_apikey'))[0];
        if (!isset($this->request->get['orderid']) || $shopid === 0) {
            die();
        }
        $orderid = (int)$this->request->get['orderid'];
        $meta = $this->db->query("SELECT * FROM $this->metaTbl WHERE orderid = $orderid AND shopid = $shopid")->row;
        $rev = $this->request->get['rev'] ?? null;

        if (isset($rev, $meta['rev']) && $rev >= $meta['rev']) {
            // Backoff strategy: .5s, 1s, 2s, 4s, 8s: Total: 15.5s
            $s = 1;
            usleep(500000); // 0.5 secs. Note: usleep is only safe below 1s
            while (1) {
                $meta = $this->db->query(
                    "SELECT * FROM $this->metaTbl WHERE orderid = $orderid AND shopid = $shopid"
                )->row;
                if ($meta['rev'] > $rev || $s > 8) {
                    break;
                }
                sleep($s);
                $s = $s + $s;
                echo "\n"; // echo + flush to detect if the client has disc.
                ob_flush();
                flush();
            }
        }
        $this->response->setOutput(json_encode($meta));
    }

    public function install() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('scanpay');
        $this->model_setting_event->addEvent(
            'scanpay',
            'catalog/model/checkout/order/addOrderHistory/after',
            'extension/payment/scanpay/captureOnOrderStatus'
        );

        $this->db->query("DROP TABLE IF EXISTS $this->seqTbl");
        $this->db->query("DROP TABLE IF EXISTS $this->metaTbl");

        $this->db->query(
            "CREATE TABLE $this->seqTbl (
                shopid  INT unsigned NOT NULL UNIQUE,
                seq     INT unsigned NOT NULL,
                ping    INT unsigned NOT NULL,
                mtime   BIGINT unsigned NOT NULL,
                PRIMARY KEY (shopid)
            ) CHARSET = latin1;"
        );

        $this->db->query(
            "CREATE TABLE $this->metaTbl (
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
        $this->db->query("DROP TABLE IF EXISTS $this->seqTbl");
        $this->db->query("DROP TABLE IF EXISTS $this->metaTbl");

        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('scanpay');
    }
}
