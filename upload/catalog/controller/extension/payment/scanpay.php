<?php

class ControllerExtensionPaymentScanpay extends Controller {
    // index(): only executed on order confirmation page (?route=checkout/confirm)
    public function index() {
        $data['action'] = $this->url->link('extension/payment/scanpay/pay', '', true);
        return $this->load->view('extension/payment/scanpay', $data);
    }

    // pay() is called on form submit ($data['action'])
    public function pay() {
        $this->load->model('extension/payment/scanpay');
        $this->response->redirect(
            $this->model_extension_payment_scanpay->newUrl(),
            302
        );
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
                            $this->model_checkout_order->addOrderHistory(
                                $orderid,
                                $this->config->get('config_order_status_id'),
                                'Scanpay: authorized ' . $change['totals']['authorized'],
                                true
                            );
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
        $apikey = (string)$this->config->get('payment_scanpay_apikey');
        $shopid = (int)explode(':', $apikey)[0];

        require_once DIR_SYSTEM . 'library/scanpay/client.php';
        require_once DIR_SYSTEM . 'library/scanpay/db.php';
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
