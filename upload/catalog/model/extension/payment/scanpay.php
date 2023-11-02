<?php

class ModelExtensionPaymentScanpay extends Model {
    public function getMethod($address, $total) {
        $this->language->load('extension/payment/scanpay');
        return [
            'code'       => 'scanpay',
            'title'      => $this->language->get('text_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_scanpay_sort_order'),
        ];
    }

    public function loadSeq(int $shopid) {
        $res = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "scanpay_seq`
            WHERE `shopid` = " . $shopid . " LIMIT 1"
        );
        if ($res->num_rows === 0) {
            return [ 'seq' => 0, 'mtime' => 0 ];
        }
        return [ 'seq' => (int)$res->rows[0]['seq'], 'mtime' => (int)$res->rows[0]['mtime'] ];
    }

    public function updateSeqMtime(int $shopid) {
        $this->db->query(
            'INSERT INTO `' . DB_PREFIX . 'scanpay_seq` (shopid, seq, mtime)
            VALUES (' . $shopid . ', 0, ' . time() . ') ON DUPLICATE KEY UPDATE
            mtime = ' . time()
        );
    }

    public function saveSeq(int $shopid, int $seq) {
        $this->db->query(
            'INSERT INTO `' . DB_PREFIX . 'scanpay_seq` (shopid, seq, mtime)
            VALUES (' . $shopid . ', ' . $seq . ', ' . time() . ') ON DUPLICATE KEY UPDATE
            shopid = IF(seq < VALUES(seq), VALUES(shopid), shopid),
            mtime = IF(seq < VALUES(seq), VALUES(mtime), mtime),
            seq    = IF(seq < VALUES(seq), VALUES(seq), seq)'
        );
    }

    public function getOrder(int $orderid) {
        $res = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "scanpay_order`
            WHERE `orderid` = " . $orderid . " LIMIT 1"
        );
        if ($res->num_rows === 0) {
            return false;
        }
        return $res->rows[0];
    }

    public function num($moneystr) {
        return (int)explode(' ', $moneystr)[0];
    }

    public function setOrder($shopid, $data) {
        $q = 'INSERT INTO `' . DB_PREFIX . 'scanpay_order` ' .
            '(orderid, shopid, trnid, rev, nacts, authorized, captured, refunded)
            VALUES (
                ' . (int)$data['orderid'] . ', 
                ' . (int)$shopid . ' ,
                ' . (int)$data['id'] . ', 
                ' . (int)$data['rev'] . ', 
                ' . count($data['acts']) . ', 
                ' . (string)$this->num($data['totals']['authorized']) . ', 
                ' . (string)$this->num($data['totals']['captured']) . ', 
                ' . (string)$this->num($data['totals']['refunded']) .
            ') ON DUPLICATE KEY UPDATE
            nacts      = IF(rev < VALUES(rev), VALUES(nacts), nacts),
            authorized = IF(rev < VALUES(rev), VALUES(authorized), authorized),
            captured   = IF(rev < VALUES(rev), VALUES(captured), captured),
            refunded   = IF(rev < VALUES(rev), VALUES(refunded), refunded),
            rev        = IF(rev < VALUES(rev), VALUES(rev), rev)';
        // Update rev last to ensure the previous IFs work
        $this->db->query($q);
    }
}
