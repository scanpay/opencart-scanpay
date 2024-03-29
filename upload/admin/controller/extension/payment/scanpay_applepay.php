<?php

class ControllerExtensionPaymentScanpayApplePay extends Controller {
    // index(): only executed in plugin settings
    public function index() {
        $this->document->setTitle('Scanpay Apple Pay');
        $this->document->addStyle('view/stylesheet/scanpay/settings.css?vEXTENSION_VERSION');
        $this->load->model('setting/setting');

        $token = $this->session->data['user_token'];
        $data = [
            'header' => $this->load->controller('common/header'),
            'column_left' => $this->load->controller('common/column_left'),
            'footer' => $this->load->controller('common/footer'),
            'action' => $this->url->link('extension/payment/scanpay_applepay', "user_token=$token"),
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
                ],
                [
                    'text' => 'Apple Pay',
                    'href' => $this->url->link('extension/payment/scanpay_applepay', "user_token=$token", true)
                ]
            ]
        ];

        $settings = [
            'payment_scanpay_applepay_status' => 0,
            'payment_scanpay_applepay_language' => 'auto',
            'payment_scanpay_applepay_sort_order' => 1
        ];
        foreach ($settings as $x => &$default) {
            $data[$x] = $this->request->post[$x] ?? $this->config->get($x) ?: $default;
        }

        // Handle save button
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if ($this->user->hasPermission('modify', 'extension/payment/scanpay_applepay')) {
                $this->model_setting_setting->editSetting('payment_scanpay_applepay', $this->request->post);
                $data['success_msg'] = 'Success: You have successfully modified your Scanpay settings!';
            } else {
                $data['error_warning'] = 'Warning: You do not have permission to modify these settings!';
            }
        }
        $this->response->setOutput($this->load->view('extension/payment/scanpay/settings_applepay', $data));
    }
}
