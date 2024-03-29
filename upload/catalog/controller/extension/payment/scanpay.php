<?php

class ControllerExtensionPaymentScanpay extends Controller {
    private $seqTbl = DB_PREFIX . 'scanpay_seq';
    private $metaTbl = DB_PREFIX . 'scanpay_order';
    private $ocTbl = DB_PREFIX . 'order';

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

    protected function changeIsValid(array $c): bool {
        if (isset($c['error'])) {
            $this->log->write("scanpay warning: transaction [id={$c['id']}] skipped due to error: {$c['error']}");
            return false;
        }
        if (
            !isset($c['rev'], $c['acts'], $c['id']) ||
            !is_int($c['rev']) || !is_int($c['id']) || !is_array($c['acts'])
        ) {
            throw new Exception('received invalid seq from server');
        }

        if ($c['type'] !== 'transaction') {
            throw new Exception("received unknown seq type: {$c['type']}");
        }

        if (!isset($c['totals'], $c['totals']['authorized'])) {
            throw new Exception('received invalid seq from server');
        }

        if (empty($c['orderid']) || !is_numeric($c['orderid'])) {
            $this->log->write("scanpay notice: transaction #{$c['id']} skipped; no valid opencart orderid");
            return false;
        }
        return true;
    }

    protected function applyChanges(int $shopid, array $arr) {
        foreach ($arr as $c) {
            if (!$this->changeIsValid($c)) {
                continue;
            }
            $orderid = (int)$c['orderid'];
            $nacts = count($c['acts']);
            $dbRev = $this->db->query("SELECT rev FROM $this->metaTbl WHERE orderid = $orderid")->row['rev'] ?? 0;

            if (!$dbRev) {
                $this->db->query(
                    "INSERT INTO $this->metaTbl
                        SET orderid = $orderid,
                            shopid = $shopid,
                            trnid = " . $c['id'] . ",
                            rev = " . $c['rev'] . ",
                            nacts = $nacts,
                            authorized = '" . $c['totals']['authorized'] . "',
                            captured = '" . $c['totals']['captured'] . "',
                            refunded = '" . $c['totals']['refunded'] . "',
                            voided = '" . $c['totals']['voided'] . "'"
                );
                $order = $this->model_checkout_order->getOrder($orderid);
                if (empty($order) || substr($order['payment_code'], 0, 7) !== 'scanpay') {
                    continue;
                }
                // Change 'scanpay_mobilepay' to 'scanpay' in the OpenCart order table
                if ($order['payment_code'] !== 'scanpay') {
                    $this->db->query("UPDATE $this->ocTbl SET payment_code = 'scanpay' WHERE order_id = $orderid");
                }
                if ($order['order_status_id'] === '0') {
                    $this->model_checkout_order->addOrderHistory(
                        $orderid,
                        $this->config->get('config_order_status_id'),
                        'Scanpay: authorized ' . $c['totals']['authorized'],
                        true
                    );
                }
            } elseif ($c['rev'] > $dbRev) {
                $this->db->query(
                    "UPDATE $this->metaTbl
                        SET rev = " . $c['rev'] . ",
                            nacts = $nacts,
                            captured = '" . $c['totals']['captured'] . "',
                            refunded = '" . $c['totals']['refunded'] . "',
                            voided = '" . $c['totals']['voided'] . "'
                        WHERE orderid = $orderid"
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

        $sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        if (!hash_equals(base64_encode(hash_hmac('sha256', $body, $apikey, true)), $sig)) {
            return $this->sendJson(['error' => 'invalid signature'], 403);
        }

        $ping = json_decode($body, true);
        if (!isset($ping, $ping['seq'], $ping['shopid']) || !is_int($ping['seq']) || $shopid !== $ping['shopid']) {
            return $this->sendJson(['error' => 'invalid JSON'], 400);
        }

        $seq = (int)$this->db->query("SELECT seq FROM $this->seqTbl WHERE shopid = $shopid")->row['seq'];
        if ($ping['seq'] === $seq) {
            $mtime = time();
            $this->db->query("UPDATE $this->seqTbl SET mtime = $mtime WHERE shopid = $shopid");
            return $this->sendJson(['success' => true], 200);
        } elseif ($ping['seq'] < $seq) {
            $errMsg = "The received ping seq ({$ping['seq']}) was smaller than the local seq ($seq)";
            $this->log->write('scanpay synchronization error: ' . $errMsg);
            return $this->sendJson(['error' => $errMsg], 400);
        }

        // Simple "filelock" with mkdir (because it's atomic, fast and dirty!)
        $flock = sys_get_temp_dir() . '/scanpay_' . $shopid . '_lock/';
        if (!@mkdir($flock) && file_exists($flock)) {
            $dtime = time() - filemtime($flock);
            if ($dtime >= 0 && $dtime < 60) {
                $this->db->query("UPDATE $this->seqTbl SET ping = " . $ping['seq'] . " WHERE shopid = $shopid");
                return $this->sendJson(['error' => 'busy'], 423);
            }
        }

        $this->load->model('checkout/order');
        require DIR_SYSTEM . 'library/scanpay/client.php';
        $client = new ScanpayClient($apikey);
        try {
            while ($seq < $ping['seq']) {
                $res = $client->seq($seq);
                if (empty($res['changes'])) {
                    break; // done
                }
                $this->applyChanges($shopid, $res['changes']);

                // Update seq in the DB
                $seq = (int)$res['seq'];
                $mtime = time();
                $this->db->query("UPDATE $this->seqTbl SET mtime = $mtime, seq = $seq WHERE shopid = $shopid");

                touch($flock);
                usleep(500000); // sleep for 500 ms (wait for changes)
                if ($seq >= $ping['seq']) {
                    $ping['seq'] = (int)$this->db->query(
                        "SELECT ping FROM $this->seqTbl WHERE shopid = $shopid"
                    )->row['ping'];
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
        $meta = $this->db->query(
            "SELECT trnid, nacts FROM $this->metaTbl WHERE orderid = $orderid AND shopid = $shopid"
        )->row;
        if ($meta['trnid']) {
            require_once DIR_SYSTEM . 'library/scanpay/client.php';
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
