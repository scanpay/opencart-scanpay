<?php

class ControllerExtensionPaymentScanpay extends Controller {
    public function index() {
        $this->language->load('extension/payment/scanpay');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/scanpay');

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if ($this->user->hasPermission('modify', 'extension/payment/scanpay')) {
                $this->model_setting_setting->editSetting('payment_scanpay', $this->request->post);
            } else {
                $this->error['warning'] = $this->language->get('error_permission');
            }
        }
        $apikey = (string)$this->config->get('payment_scanpay_apikey');
        $shopIdStr = explode(':', $apikey)[0];

        if (ctype_digit($shopIdStr) && (string)(int)$shopIdStr == $shopIdStr) {
            $seqObj = $this->model_extension_payment_scanpay->getSeq((int)$shopIdStr);
            $mtime = $seqObj['mtime'];
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
        $this->response->setOutput($this->load->view('extension/payment/scanpay', $data));
    }

    protected function fmtdt(int $dt) {
        $minute = 60;
        $hour = $minute * 60;
        $day = $hour * 24;
        if ($dt <= 1) {
            return '1 second ago';
        } elseif ($dt < $minute) {
            return (string)$dt . ' seconds ago';
        } elseif ($dt < $minute + 30) {
            return '1 minute ago';
        } elseif ($dt < $hour) {
            return (string)round((float)$dt / $minute) . ' minutes ago';
        } elseif ($dt < $hour + 30 * $minute) {
            return '1 hour ago';
        } elseif ($dt < $day) {
            return (string)round((float)$dt / $hour) . ' hours ago';
        } elseif ($dt < $day + 12 * $hour) {
            return '1 day ago';
        } else {
            return (string)round((float)$dt / $day) . ' days ago';
        }
    }

    protected function pingstatus(int $mtime) {
        $t = time();
        if ($mtime > $t) {
            $this->log->write('last modified time is in the future');
            return;
        }
        if ($t < $mtime + 900) {
            return 'ok';
        } elseif ($t < $mtime + 3600) {
            return 'warning';
        } elseif ($mtime > 0) {
            return 'error';
        } else {
            return 'never--pinged';
        }
    }

    public function order() {
		if ($this->config->get('payment_scanpay_status')) {
            $orderid = $this->request->get['order_id'];
            $apikey = $this->config->get('payment_scanpay_apikey');
            $shopid = (int)explode(':', $apikey)[0];
            $this->load->model('extension/payment/scanpay');
            $data = $this->model_extension_payment_scanpay->getOrderMeta($orderid, $shopid);
            $data['user_token'] = $this->session->data['user_token'];
            if (isset($data['trnid'])) {
                return $this->load->view('extension/payment/scanpay_order', $data);
            }
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
        $this->load->model('extension/payment/scanpay');
        $this->model_extension_payment_scanpay->install();
    }

    public function uninstall() {
        // Delete old databases (tmpfix)
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_seq");
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_order");

        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('scanpay');
    }
}
