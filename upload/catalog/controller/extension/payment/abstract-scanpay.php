<?php

abstract class AbstractControllerExtensionPaymentScanpay extends Controller {
    const ORDER_STATUS_PENDING = 1;
    const ORDER_STATUS_PROCESSING = 2;
    const ORDER_STATUS_REFUNDED = 11;
    const ORDER_STATUS_VOIDED = 16;


    public function _index($paymethod = '') {
        $suffix = ($paymethod) ? ('_' . $paymethod) : '';
        $this->language->load('extension/payment/scanpay' . $suffix);
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['action'] = $this->url->link('extension/payment/scanpay' . $suffix . '/pay', '', true);
        return $this->load->view('extension/payment/scanpay' . $suffix, $data);
    }

    protected function mkpayment($paymethod='') {
        $this->load->model('checkout/order');
        $this->load->library('scanpay');

        $orderid = $this->session->data['order_id'];
        $order = $this->model_checkout_order->getOrder($orderid);

        $products = $this->model_checkout_order->getOrderProducts($orderid);
        $totals = $this->model_checkout_order->getOrderTotals($orderid);
        $apikey = $this->config->get('payment_scanpay_apikey');

        $data = [
            'orderid'     => $orderid,
            'language'    => $this->config->get('payment_scanpay_language'),
            'successurl'  => $this->url->link('extension/payment/scanpay/success'),
            'autocapture' => (bool)$this->config->get('payment_scanpay_autocapture'),
            'billing'     => array_filter([
                'name'    => $order['payment_firstname'] . ' ' . $order['payment_lastname'],
                'email'   => $order['email'],
                'phone'   => preg_replace('/\s+/', '', $order['telephone']),
                'address' => array_filter([ $order['payment_address_1'], $order['payment_address_2']] ),
                'city'    => $order['payment_city'],
                'zip'     => $order['payment_postcode'],
                'country' => $order['payment_country'],
                'state'   => $order['payment_zone'],
                'company' => $order['payment_company'],
                'vatin'   => '',
                'gln'     => '',
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
        ];
        $discounts = $this->getdiscounts($products);

        $items = [];
        /* Add the requested items to the request data */
        foreach ($products as $i => $product) {
            if ($product['total'] < 0) {
                $this->log->write('Cannot handle negative price for item');
                throw new \Exception(__('Internal server error', 'woocommerce-scanpay'));
            }

            $items[] = [
                'name'     => $product['name'],
                'quantity' => intval($product['quantity']),
                'total'    => $product['total'] + $product['tax'] * $product['quantity'] - $discounts['items'][$i],
                'sku'      => strval($product['product_id']),
            ];
        }

        /* Add shipping costs */
        if (isset($this->session->data['shipping_method'])) {
            $cost = $this->session->data['shipping_method']['cost'];
            $taxed = $this->tax->calculate($cost, $this->session->data['shipping_method']['tax_class_id']);
            $items[] = [
                'name'     => $this->session->data['shipping_method']['title'],
                'quantity' => 1,
                'total'    => $discounts['freeshipping'] ? 0 : $taxed,
            ];
        }

        /* Distribute voucher and reward subtracts */
        foreach ($totals as $total) {
            if ($total['code'] === 'voucher' || $total['code'] === 'reward') {
                $items = $this->distributeamount($items, $total['value']);
            }
        }

        /* Calculat grand total and round item totals */
        $grandtotal = 0;
        foreach ($items as $i => $item) {
            $items[$i]['total'] = round($items[$i]['total'], 2);
            $grandtotal += $items[$i]['total'];
        }

        /* Better round some more due to devious floats */
        $grandtotal = round($grandtotal, 2);
        $ordertotal = round($order['total'], 2);

        /* If the calculated grand total differs from the order total, compensate
           by adding / subtracting amounts from items. Also convert to string
           before comparing since float compare often will not yield the right result. */
        if ($grandtotal . '' !== $ordertotal . '') {
            $totdiff = round($ordertotal - $grandtotal, 2);

            foreach ($items as $i => $item) {
                /* We bound the minimum item total at 0, by bounding
                   the difference at minus the current item total */
                $d = max($totdiff, -$items[$i]['total']);
                $items[$i]['total'] += $d;
                $totdiff = round($totdiff - $d, 2);
            }
        }
        /* Add currencies to totals */
        foreach ($items as $i => $item) {
            $items[$i]['total'] .= ' ' . $order['currency_code'];
        }
        $data['items'] = $items;

        $client = new Scanpay\Scanpay($apikey);
        try {
            $opts = [
                'headers' => [
                    'X-Shop-Plugin' => 'opencart/' . SCANPAY_VERSION,
                    'X-Cardholder-IP:' => $order['ip'],
                ]
            ];
            $payurl = $client->newURL(array_filter($data), $opts);
        } catch (\Exception $e) {
            $this->log->write('scanpay client exception: ' . $e->getMessage());
            $this->response->redirect($this->url->link('extension/payment/failure', '', true));
        }

        $this->model_checkout_order->addOrderHistory($orderid, self::ORDER_STATUS_PENDING);
        if ($paymethod != '') { $payurl .= '?go=' . $paymethod; }
        $this->response->redirect($payurl, 302);
    }

    protected function distributeamount($items, $amount) {
        /* Copy items into $ret */
        $ret = $items;
        $itemtotals = array_fill(0, count($items), 0);
        $grandtotal = 0;
        /* Calculate grand total from line totals */
        foreach ($items as $i => $item) {
            $grandtotal += $item['total'];
        }

        /* Distribute the discount based on the share of the grand total */
        foreach ($items as $i => $item) {
            $share = $item['total'] / $grandtotal;
            $ret[$i]['total'] = $item['total'] + $amount * $share;
        }

        return $ret;
    }

    protected function getdiscounts($items) {
        /* Create discounts array ( +1 field for shipping) */
        $discounts = array_fill(0, count($items), 0);
        if (!isset($this->session->data['coupon'])) { return [ 'items' => $discounts, 'freeshipping' => 0 ]; }
        $this->load->model('extension/total/coupon');
        $this->load->model('catalog/product');

        $coupon = $this->model_extension_total_coupon->getCoupon($this->session->data['coupon']);
        if ($coupon === null) { return [ 'items' => $discounts, 'freeshipping' => 0 ]; }
        /* Calculate subtotal */
        $subtotal = 0;
        foreach ($items as $item) {
            $product = $this->model_catalog_product->getProduct($item['product_id']);
            if ($coupon['product'] && !in_array($product['product_id'], $coupon['product'])) {
                continue;
            }
            $subtotal += $item['total'];
        }

        foreach ($items as $i => $item) {
            $product = $this->model_catalog_product->getProduct($item['product_id']);
            /* Ignore discount if it is product-specific and does not apply to this product  */
            if ($coupon['product'] && !in_array($product['product_id'], $coupon['product'])) {
                continue;
            }
            /* Calculate per item discount */
            if ($coupon['type'] === 'F') {
                $discounts[$i] = $coupon['discount'] * ($item['total'] / $subtotal);
            } else if ($coupon['type'] == 'P') {
                $discounts[$i] = $item['total'] / 100 * $coupon['discount'];
            }
            if ($product['tax_class_id']) {
                $tax_rates = $this->tax->getRates($discounts[$i], $product['tax_class_id']);
                foreach ($tax_rates as $tax_rate) {
                    /* IF the tax is procentual, increase the discount by applying tax to the discounted amount and subtracting it */
                    if ($tax_rate['type'] === 'P') {
                        $discounts[$i] += $tax_rate['amount'];
                    }
                }
            }
        }
        return [
            'items'        => $discounts,
            'freeshipping' => $coupon['shipping'],
        ];
    }

    public function success() {
        $this->response->redirect($this->url->link('checkout/success'));
    }

    /*
     * Ping/seq related functions
     */

    protected function sendJSON($ent, $code) {
        http_response_code($code);
        $this->response->setOutput(json_encode($ent));
    }

    public function ping() {
        $this->load->model('checkout/order');
        $this->load->library('scanpay');
        $this->load->model('extension/payment/scanpay');

        $client = new Scanpay\Scanpay($this->config->get('payment_scanpay_apikey'));
        try {
            $pingobj = $client->handlePing();
            $shopid = $pingobj['shopid'];
            $remoteSeq = $pingobj['seq'];
        } catch(\Exception $e) {
            $this->sendJSON(['error' => $e->getMessage()], 403);
            return;
        }

        $localSeqObj = $this->model_extension_payment_scanpay->loadSeq($shopid);
        $localSeq = $localSeqObj['seq'];
        if ($localSeq === $remoteSeq) {
            $this->model_extension_payment_scanpay->updateSeqMtime($shopid);
        }

        while ($localSeq < $remoteSeq) {
            try {
                $opts = [
                    'headers' => [
                        'X-Shop-Plugin' => 'opencart/' . SCANPAY_VERSION,
                    ]
                ];
                $resobj = $client->seq($localSeq, $opts);
            } catch (\Exception $e) {
                $this->log->write('scanpay client exception: ' . $e->getMessage());
                $this->sendJSON(['error' => 'scanpay client exception: ' . $e->getMessage()], 500);
                return;
            }
            foreach ($resobj['changes'] as $change) {
                if (!$this->updateOrder($shopid, $change)) {
                    return;
                }
            }
            if (!$this->model_extension_payment_scanpay->saveSeq($shopid, $resobj['seq'])) {
                if ($resobj['seq']!== $localSeq) {
                    $this->sendJSON(['error' => 'error saving Scanpay changes'], 500);
                    return;
                }
                break;
            }
            $localSeq = $resobj['seq'];
        }
        $this->sendJSON(['success' => true], 200);
    }

    protected function is_assoc(array $array){
        // Keys of the array
        $keys = array_keys($array);

        // If the array keys of the keys match the keys, then the array must
        // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
        return array_keys($keys) !== $keys;
    }

    protected function is_scanpay_money($str) {
        return preg_match('/^\d+\.*\d*\ [A-Z]{3}$/', $str) === 1;
    }

    protected function valid_order($data) {
        return isset($data['id']) && is_int($data['id']) &&
            isset($data['totals']) && is_array($data['totals']) &&
            isset($data['totals']['authorized']) && $this->is_scanpay_money($data['totals']['authorized']) &&
            isset($data['totals']['captured']) && $this->is_scanpay_money($data['totals']['captured']) &&
            isset($data['totals']['refunded']) && $this->is_scanpay_money($data['totals']['refunded']) &&
            isset($data['acts']) && !$this->is_assoc($data['acts']) &&
            isset($data['rev']) && is_int($data['rev']);
    }

    protected function updateOrder($shopid, $data) {

        /* Ignore errornous transactions */
        if (isset($data['error'])) {
            $this->log->write('Received error entry in order update: ' . $data['error']);
            return true;
        }

        /* Validate the data object */
        if (!$this->valid_order($data)) {
            $this->log->write('Received invalid order data from Scanpay');
            return false;
        }

        $trnId = $data['id'];
        /* Ignore transactions without order ids */
        if (!isset($data['orderid']) || $data['orderid'] === "") {
            $this->log->write('Received transaction #' . $trnId . ' without orderid');
            return true;
        }
        if ((string)(int)$data['orderid'] != $data['orderid']) {
            $this->log->write('Non-numeric orderid is not in system');
            return true;
        }
        $data['orderid'] = (int)$data['orderid'];
        $order = $this->model_checkout_order->getOrder($data['orderid']);
        if ($order === false) {
            $this->log->write('Orderid is not in system');
            return true;
        }
        $olddata = $this->model_extension_payment_scanpay->getOrder($data['orderid']);
        if ($olddata === false) {
            $olddata = [
                'shopid' => $shopid,
                'rev'   => 0,
                'nacts' => 0,
            ];
        }
        if ($shopid !== (int)$olddata['shopid']) {
            $this->log->write('Order #' . $data['orderid'] . ' shopid (' . $olddata['shopid'] .
                ') does not match seq shopid (' . $shopid . ')');
            return true;
        }
        if ($data['rev'] <= (int)$olddata['rev']) {
            return true;
        }
        /* Check if the transaction is already registered */
        if ((int)$order['order_status_id'] === self::ORDER_STATUS_PENDING) {
            $this->model_checkout_order->addOrderHistory($data['orderid'], self::ORDER_STATUS_PROCESSING, '', true);
        }

        if (isset($data['acts']) && is_array($data['acts'])) {
            for ($i = (int)$olddata['nacts']; $i < count($data['acts']); $i++) {
                $act = $data['acts'][$i];
                switch ($act['act']) {
                case 'capture':
                    break;
                case 'refund':
                    $this->model_checkout_order->addOrderHistory($data['orderid'], self::ORDER_STATUS_REFUNDED);
                    break;
                case 'void':
                    $this->model_checkout_order->addOrderHistory($data['orderid'], self::ORDER_STATUS_VOIDED);
                    break;
                }
            }
        }
        $this->model_extension_payment_scanpay->setOrder($shopid, $data);
        return true;
    }


}
