<?php

class ControllerExtensionPaymentScanpayMobilepay extends Controller {
    protected function getName() {
        return 'scanpay_mobilepay';
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/scanpay_mobilepay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    protected function index() {
        $data = [];
        $suffix = '_mobilepay';
        $configfields = ['payment_scanpay_mobilepay_status', 'payment_scanpay_mobilepay_sort_order'];

        $this->language->load('extension/payment/scanpay');
        $this->language->load('extension/payment/scanpay' . $suffix);

        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_scanpay' . $suffix, $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect(
                $this->url->link(
                    'marketplace/extension',
                    'user_token=' . $this->session->data['user_token']  . '&type=payment',
                    true
                )
            );
        }
        $data['action'] = $this->url->link(
            'extension/payment/scanpay' . $suffix,
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

        $this->response->setOutput($this->load->view('extension/payment/scanpay' . $suffix, $data));
    }

    protected function fillconfigdata(array $data, array $arr) {
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
    }

    public function uninstall() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode($this->getName());
    }
}
