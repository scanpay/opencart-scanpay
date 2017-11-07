<?php

abstract class AbstractControllerExtensionPaymentScanpay extends Controller {

    protected function _index($paymethod = '', $configfields = [], $data = []) {
        $this->language->load('extension/payment/scanpay');
        $suffix = '';
        if ($paymethod) {
            $suffix = '_' . $paymethod;
            $this->language->load('extension/payment/scanpay' . $suffix);
        }
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_scanpay' . $suffix, $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token']  . '&type=payment', true));
        }
        $data['action'] = $this->url->link('extension/payment/scanpay' . $suffix, 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $data = $this->fillconfigdata($data, $configfields);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/payment/scanpay' . $suffix, $data));
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

}
