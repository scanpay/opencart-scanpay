<?php

class ModelExtensionPaymentScanpay extends Model {
    /*
        getMethod() used in checkout to show payment method
        index.php?route=checkout/payment_method
    */
    public function getMethod($address, $total): array {
        $this->language->load('extension/payment/scanpay');
        return [
            'code'       => 'scanpay',
            'title'      => $this->language->get('text_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_scanpay_sort_order'),
        ];
    }

    public function newUrl(string $type = null): string {
        require DIR_SYSTEM . 'library/scanpay/client.php';
        $this->load->model('checkout/order');
        try {
            $orderid = $this->session->data['order_id'];
            $order = $this->model_checkout_order->getOrder($orderid);
            $total = $this->currency->format($order['total'], $order['currency_code'], '', false);
            $data = [
                'orderid'     => $orderid,
                'language'    => $this->config->get('payment_scanpay_language'),
                'successurl'  => $this->url->link('extension/payment/scanpay/success'),
                'billing'     => array_filter([
                    'name'    => $order['payment_firstname'] . ' ' . $order['payment_lastname'],
                    'email'   => $order['email'],
                    'phone'   => preg_replace('/\s+/', '', (string)$order['telephone']),
                    'address' => array_filter([ $order['payment_address_1'], $order['payment_address_2']]),
                    'city'    => $order['payment_city'],
                    'zip'     => $order['payment_postcode'],
                    'country' => $order['payment_country'],
                    'state'   => $order['payment_zone'],
                    'company' => $order['payment_company'],
                ]),
                'shipping'    => array_filter([
                    'name'    => $order['shipping_firstname'] . ' ' . $order['shipping_lastname'],
                    'address' => array_filter([ $order['shipping_address_1'], $order['shipping_address_2'] ]),
                    'city'    => $order['shipping_city'],
                    'zip'     => $order['shipping_postcode'],
                    'country' => $order['shipping_country'],
                    'state'   => $order['shipping_zone'],
                    'company' => $order['shipping_company'],
                ]),
                'items' => [[
                    'name' => "Order #$orderid",
                    'total' => $total . ' ' . $order['currency_code']
                ]]
            ];
            $apikey = $this->config->get('payment_scanpay_apikey');
            $client = new ScanpayClient($apikey);
            $url = $client->newURL(
                array_filter($data),
                ['headers' => ['X-Cardholder-IP:' => $order['ip']]]
            );
            return ($type) ? $url . '?go=' . $type : $url;
        } catch (\Exception $e) {
            $this->language->load('extension/payment/scanpay');
            $this->log->write('scanpay error: paylink failed => ' . $e->getMessage());
            $this->session->data['error'] = $this->language->get('error_failed') . '"' . $e->getMessage() . '"';
            return $this->url->link('checkout/checkout', '', true);
        }
    }
}
