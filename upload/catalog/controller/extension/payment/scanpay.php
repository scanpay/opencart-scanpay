<?php

class ControllerExtensionPaymentScanpay extends Controller {
    // index(): only executed on order confirmation page (?route=checkout/confirm)
    public function index() {
        $data['action'] = $this->url->link('extension/payment/scanpay/pay', '', true);
        return $this->load->view('extension/payment/scanpay', $data);
    }

    // pay() is called on form submit ($data['action'])
    public function pay() {
        require DIR_SYSTEM . 'library/scanpay/client.php';
        $this->load->model('checkout/order');
        $client = new ScanpayClient($this->config->get('payment_scanpay_apikey'));
        $orderid = $this->session->data['order_id'];
        $order = $this->model_checkout_order->getOrder($orderid);
        $total = $this->currency->format($order['total'], $order['currency_code'], '', false);
        $phone = preg_replace('/\s+/', '', (string)$order['telephone']);

        // Add country code for MobilePay (DK only)
        if (!empty($phone) && $order['payment_iso_code_2'] === 'DK') {
            $firstNumber = substr($phone, 0, 1);
            if ($firstNumber !== '+' && $firstNumber !== '0') {
                $phone = '+45' . $phone;
            }
        }
        $data = [
            'orderid'     => $orderid,
            'language'    => $this->config->get('payment_scanpay_language'),
            'successurl'  => $this->url->link('extension/payment/scanpay/success'),
            'billing'     => array_filter([
                'name'    => $order['payment_firstname'] . ' ' . $order['payment_lastname'],
                'email'   => $order['email'],
                'phone'   => $phone,
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
            $url = $client->newURL(array_filter($data), ['headers' => ['X-Cardholder-IP:' => $order['ip']]]);
            if (isset($this->request->get['scanpay_go'])) {
                $url .= '?go=' . $this->request->get['scanpay_go'];
            }
            $this->response->redirect($url, 302);
        } catch (\Exception $e) {
            $this->log->write('scanpay error: paylink failed => ' . $e->getMessage());
            $this->language->load('extension/payment/scanpay');
            $this->session->data['error'] = $this->language->get('error_failed') . '"' . $e->getMessage() . '"';
            $this->response->redirect($this->url->link('checkout/checkout', '', true), 302);
        }
    }

    public function success() {
        $this->response->redirect($this->url->link('checkout/success'));
    }

    protected function sendJson(array $data, int $code = 200) {
        http_response_code($code);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    protected function applyChanges(int $shopid, array $arr) {
        foreach ($arr as $change) {
            if (!$this->changeIsValid($change)) {
                continue;
            }
            $orderid = (int)$change['orderid'];
            $order = $this->model_checkout_order->getOrder($orderid);
            if (empty($order) || substr($order['payment_code'], 0, 7) !== 'scanpay') {
                continue;
            }

            // Change 'scanpay_mobilepay' to 'scanpay' (no other way, sadly)
            if ($order['payment_code'] !== 'scanpay') {
                $this->db->query(
                    "UPDATE " . DB_PREFIX . "order
                    SET payment_code = 'scanpay'
                    WHERE order_id = $orderid"
                );
            }

            $rev = (int)$change['rev'];
            $nacts = count($change['acts']);
            $this->db->query(
                "INSERT INTO " . DB_PREFIX . "scanpay_order
                    SET
                        orderid = $orderid,
                        shopid = $shopid,
                        trnid = '" . (int)$change['id'] . "',
                        rev = $rev,
                        nacts = $nacts,
                        authorized = '" . $change['totals']['authorized'] . "',
                        captured = '" . $change['totals']['captured'] . "',
                        refunded = '" . $change['totals']['refunded'] . "',
                        voided = '" . $change['totals']['voided'] . "'
                    ON DUPLICATE KEY UPDATE
                        rev = $rev,
                        nacts = $nacts,
                        authorized = '" . $change['totals']['authorized'] . "',
                        captured = '" . $change['totals']['captured'] . "',
                        refunded = '" . $change['totals']['refunded'] . "',
                        voided = '" . $change['totals']['voided'] . "'"
            );

            if ($order['order_status_id'] === '0') {
                $this->model_checkout_order->addOrderHistory(
                    $orderid,
                    $this->config->get('config_order_status_id'),
                    'Scanpay: authorized ' . $change['totals']['authorized'],
                    true
                );
            }
        }
    }

    public function ping() {
        set_time_limit(0);
        ignore_user_abort(true);
        $apikey = (string)$this->config->get('payment_scanpay_apikey');
        $shopid = (int)explode(':', $apikey)[0];
        $body = file_get_contents('php://input', false, null, 0, 512);

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
        require DIR_SYSTEM . 'library/scanpay/db.php';
        $dbi = new ScanpayDb($this->db, $shopid);
        $dbi->savePing($ping);
        $seq = $dbi->getSeq()['seq'];

        if ($ping['seq'] === $seq) {
            return $this->sendJson(['success' => true], 200);
        }

        //Simple "filelock" with mkdir (because it's atomic, fast and dirty!)
        $flock = sys_get_temp_dir() . '/scanpay_' . $shopid . '_lock/';
        if (!@mkdir($flock) && file_exists($flock)) {
            $dtime = time() - filemtime($flock);
            if ($dtime >= 0 && $dtime < 60) {
                return $this->sendJson(['error' => 'busy'], 423);
            }
        }

        $this->load->model('checkout/order');
        require DIR_SYSTEM . 'library/scanpay/client.php';
        $client = new ScanpayClient($apikey);

        try {
            while ($seq < $ping['seq']) {
                $res = $client->seq($seq);
                if (count($res['changes']) === 0) {
                    break; // done
                }
                $this->applyChanges($shopid, $res['changes']);
                $seq = $res['seq'];
                $dbi->setSeq($seq);
                touch($flock);
                usleep(500000); // sleep for 500 ms
                if ($seq === $ping['seq']) {
                    $ping['seq'] = $dbi->getSeq()['ping'];
                }
            }
            rmdir($flock);
            return $this->sendJson(['success' => true], 200);
        } catch (\Exception $e) {
            rmdir($flock);
            $this->log->write('scanpay synchronization error: ' . $e->getMessage());
            return $this->sendJson(['error' => $e->getMessage()], 500);
        }
    }

    protected function changeIsValid(array $change): bool {
        if ($change['type'] !== 'transaction') {
            $this->log->write('scanpay error: Received unsupported seq type: ' . $change['type']);
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
        $apikey = (string)$this->config->get('payment_scanpay_apikey');
        $shopid = (int)explode(':', $apikey)[0];

        require_once DIR_SYSTEM . 'library/scanpay/client.php';
        require_once DIR_SYSTEM . 'library/scanpay/db.php';
        $dbi = new ScanpayDb($this->db, $shopid);
        $meta = $dbi->getMeta($orderid);

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
