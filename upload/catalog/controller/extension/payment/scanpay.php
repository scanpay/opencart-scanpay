<?php

class ControllerExtensionPaymentScanpay extends Controller {
    /*
        index(): only executed on order confirmation page
        index.php?route=checkout/confirm
    */
    public function index() {
        $this->language->load('extension/payment/scanpay');
        $this->load->model('checkout/order');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['action'] = $this->url->link('extension/payment/scanpay/pay', '', true);
        return $this->load->view('extension/payment/scanpay', $data);
    }

    /*
        pay(): button action
        index.php?route=extension/payment/scanpay/pay
    */
    public function pay() {
        $this->load->model('checkout/order');
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

        try {
            require DIR_SYSTEM . 'library/scanpay/client.php';
            $apikey = $this->config->get('payment_scanpay_apikey');
            $client = new ScanpayClient($apikey);
            $paylink = $client->newURL(
                array_filter($data),
                ['headers' => ['X-Cardholder-IP:' => $order['ip']]]
            );
        } catch (\Exception $e) {
            $this->language->load('extension/payment/scanpay');
            $this->log->write('scanpay error: paylink failed => ' . $e->getMessage());
            $this->session->data['error'] = $this->language->get('error_failed') . '"' . $e->getMessage() . '"';
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
        $this->response->redirect($paylink, 302);
    }

    public function success() {
        $this->response->redirect($this->url->link('checkout/success'));
    }

    protected function sendJson(array $data, int $code = 200) {
        http_response_code($code);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    public function ping() {
        set_time_limit(0);
        ignore_user_abort(true);
        $apikey = (string)$this->config->get('payment_scanpay_apikey');
        $shopid = (int)explode(':', $apikey)[0];
        $body = file_get_contents('php://input', false, null, 0, 512); // valid pings are <512 bytes

        if (
            $shopid === 0 || !isset($_SERVER['HTTP_X_SIGNATURE']) ||
            !hash_equals(base64_encode(hash_hmac('sha256', $body, $apikey, true)), $_SERVER['HTTP_X_SIGNATURE'])
        ) {
            return $this->sendJson(['error' => 'invalid signature'], 403);
        }

        $ping = json_decode($body, true);
        if (!isset($ping, $ping['seq'], $ping['shopid']) || !is_int($ping['seq']) || $shopid !== $ping['shopid']) {
            return $this->sendJson(['error' => 'invalid JSON'], 400);
        }

        require DIR_SYSTEM . 'library/scanpay/client.php';
        require DIR_SYSTEM . 'library/scanpay/db.php';
        $client = new ScanpayClient($apikey);
        $sdb = new ScanpayDb($this->db, $shopid);
        $sdb->lock($this); // lock or die()
        $seq = $sdb->getSeq()['seq'];
        if ($ping['seq'] === $seq) {
            $sdb->setSeq($seq); // update mtime
            $sdb->unlock();
            return $this->sendJson(['success' => true], 200);
        }
        $this->load->model('checkout/order');

        try {
            while (1) {
                $res = $client->seq($seq);
                if (count($res['changes']) === 0) {
                    break; // done
                }
                foreach ($res['changes'] as $change) {
                    if (!$this->changeIsValid($change) || $change['type'] !== 'transaction') {
                        continue;
                    }
                    $orderid = (int)$change['orderid'];
                    $order = $this->model_checkout_order->getOrder($orderid);
                    if (!empty($order) && $order['payment_code'] === 'scanpay') {
                        $sdb->setMeta($orderid, $change);
                        if ($order['order_status_id'] === '0') {
                            $msg = 'Scanpay: authorized ' . $change['totals']['authorized'];
                            $status = (int)$this->config->get('payment_scanpay_auth_status');
                            $this->model_checkout_order->addOrderHistory($orderid, $status, $msg, true);
                        }
                    }
                }
                $seq = $res['seq'];
                $sdb->setSeq($seq);
            }
            $sdb->unlock();
            return $this->sendJson(['success' => true], 200);
        } catch (\Exception $e) {
            $sdb->unlock();
            $this->log->write('scanpay synchronization error: ' . $e->getMessage());
            return $this->sendJson(['error' => $e->getMessage()], 500);
        }
    }

    protected function changeIsValid(array $change): bool {
        if ($change['type'] !== 'transaction' && $change['type'] !== 'charge' && $change['type'] !== 'subscriber') {
            $this->log->write('scanpay error: Received unknown seq type: ' . $change['type']);
            die();
        }
        if (isset($change['error'])) {
            $this->log->write("scanpay error: transaction [id=$change[id]] skipped due to error: $change[error]");
            return false;
        }
        if (!isset($change['id']) || !is_int($change['id'])) {
            $this->log->write("scanpay error: Synchronization failed: missing 'id' in transaction");
            die();
        }
        if (!isset($change['rev'], $change['acts']) || !is_int($change['rev']) || !is_array($change['acts'])) {
            $this->log->write("scanpay error: Synchronization failed: received invalid seq from server");
            die();
        }
        if ($change['type'] === 'subscriber') {
            if (!isset($change['ref'])) {
                $this->log->write('scanpay warning: Received subscriber #' . $change['id'] . ' without ref');
                return false;
            }
        } else {
            if (empty($change['orderid']) || !is_numeric($change['orderid'])) {
                $this->log->write("scanpay notice: transaction #{$change['id']} does not have an opencart orderid");
                return true;
            }
            if (!isset($change['totals']['authorized'])) {
                $this->log->write('scanpay error: received invalid seq from server');
                die();
            }
        }
        return true;
    }

    public function captureOnOrderStatus($_route, array $data) {
        $orderid = (int)$data[0];
        $order = $this->model_checkout_order->getOrder($orderid);
        if (
            $order === false || $order['payment_code'] !== 'scanpay' ||
            $order['order_status_id'] !== $this->config->get('payment_scanpay_auto_capture')
        ) {
            return;
        }
        $apikey = $this->config->get('payment_scanpay_apikey');
        $shopid = (int)explode(':', $apikey)[0];

        require DIR_SYSTEM . 'library/scanpay/client.php';
        require DIR_SYSTEM . 'library/scanpay/db.php';
        $sdb = new ScanpayDb($this->db, $shopid);
        $meta = $sdb->getMeta($orderid);

        if (isset($meta['trnid'])) {
            try {
                $client = new ScanpayClient($apikey);
                $client->capture($meta['trnid'], [
                    'total' => round($order['total'], 2) . ' ' . $order['currency_code'],
                    'index' => $meta['nacts'],
                ]);
            } catch (\Exception $e) {
                $this->log->write('Scanpay: auto-capture failed: ' . $e->getMessage());
            }
        }
    }
}
