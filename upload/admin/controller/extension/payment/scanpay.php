<?php

class ControllerExtensionPaymentScanpay extends Controller {
    protected function getName() {
        return 'scanpay';
    }

    public function index() {
        $this->load->model('extension/payment/scanpay');
        $this->document->addScript('view/javascript/scanpay.js');
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
        $configfields = ['payment_scanpay_status', 'payment_scanpay_language', 'payment_scanpay_apikey',
            'payment_scanpay_captureonorderstatus', 'payment_scanpay_autocapture', 'payment_scanpay_sort_order'];

        $this->language->load('extension/payment/scanpay');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_scanpay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link(
                'marketplace/extension',
                'user_token=' . $this->session->data['user_token']  . '&type=payment',
                true
            ));
        }
        $data['action'] = $this->url->link(
            'extension/payment/scanpay',
            'user_token=' . $this->session->data['user_token'],
            true
        );
        $data['cancel'] = $this->url->link(
            'marketplace/extension',
            'user_token=' . $this->session->data['user_token'] . '&type=payment',
            true
        );

        $data = $this->fillconfigdata($data, $configfields);
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $this->response->setOutput($this->load->view('extension/payment/scanpay', $data));
    }

    protected function fmtdt($dt)
    {
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

    protected function pingstatus($mtime) {
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

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/scanpay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        if (!$this->request->post['payment_scanpay_apikey']) {
            $this->error['payment_scanpay_apikey'] = $this->language->get('error_apikey');
        }
        return !$this->error;
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

    public function install() {
        $this->load->model('setting/event');
        $moduleName = $this->getName();
        $this->model_setting_event->deleteEventByCode($moduleName);
        $this->model_setting_event->addEvent(
            $moduleName,
            'catalog/model/checkout/order/addOrderHistory/after',
            'extension/payment/' . $moduleName . '/captureOnOrderStatus'
        );
        $this->load->model('extension/payment/scanpay');
        $this->model_extension_payment_scanpay->install();
    }

    public function uninstall() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode($this->getName());
    }
}
