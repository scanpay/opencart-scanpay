<?php
class ModelExtensionPaymentScanpay extends Model {
    const TABLE_SCANPAY_ORDER = 'scanpay_order';

    public function getMethod($address, $total) {
        $this->language->load('extension/payment/scanpay');
        return [
            'code'       => 'scanpay',
            'title'      => $this->language->get('text_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_scanpay_sort_order'),
        ];
    }

    protected function requireInts($arr) {
        foreach ($arr as $v) {
            if (!is_int($v)) { throw new \Exception('value is not an int'); }
        }
    }

    public function loadSeq($shopid) {
        $this->requireInts([ $shopid ]);
        $res = $this->db->query("SELECT * FROM `" . DB_PREFIX . "scanpay_seq` WHERE `shopid` = " . $shopid . " LIMIT 1");
        if ($res->num_rows === 0) { return [ 'seq' => 0, 'mtime' => 0 ]; }
        return [ 'seq' => (int)$res->rows[0]['seq'], 'mtime' => (int)$res->rows[0]['mtime'] ];
    }

    public function updateSeqMtime($shopid) {
        $this->requireInts([ $shopid ]);
        $this->db->query('INSERT INTO `' . DB_PREFIX . 'scanpay_seq` (shopid, seq, mtime)
            VALUES (' . $shopid . ', 0, ' . time() . ') ON DUPLICATE KEY UPDATE
            mtime = ' . time());
    }

    public function saveSeq($shopid, $seq) {
        $this->requireInts([ $shopid, $seq ]);
        $this->db->query('INSERT INTO `' . DB_PREFIX . 'scanpay_seq` (shopid, seq, mtime)
            VALUES (' . $shopid . ', ' . $seq . ', ' . time() . ') ON DUPLICATE KEY UPDATE
            shopid = IF(seq < VALUES(seq), VALUES(shopid), shopid),
            mtime = IF(seq < VALUES(seq), VALUES(mtime), mtime),
            seq    = IF(seq < VALUES(seq), VALUES(seq), seq)');
    }

    public function getOrder($orderid) {
        $this->requireInts([ $orderid ]);
        $res = $this->db->query("SELECT * FROM `" . DB_PREFIX . self::TABLE_SCANPAY_ORDER . "` WHERE `orderid` = " . $orderid . " LIMIT 1");
        if ($res->num_rows === 0) { return false; }
        return $res->rows[0];
    }

    public function num($moneystr) {
        return (int)explode(' ', $moneystr)[0];
    }

    public function setOrder($shopid, $data) {
        $this->requireInts([ $data['orderid'], $shopid, $data['id'], $data['rev'] ]);
        $q = 'INSERT INTO `' . DB_PREFIX . self::TABLE_SCANPAY_ORDER  . '` (orderid, shopid, trnid, rev, nacts, authorized, captured, refunded)
            VALUES (' . $data['orderid'] . ', ' . $shopid . ' ,' . $data['id'] . ', ' . $data['rev'] . ', ' . count($data['acts']) . ', ' .
            $this->num($data['totals']['authorized']) . ', ' . $this->num($data['totals']['captured']) . ', ' . $this->num($data['totals']['refunded']) .
            ') ON DUPLICATE KEY UPDATE
            nacts      = IF(rev < VALUES(rev), VALUES(nacts), nacts),
            authorized = IF(rev < VALUES(rev), VALUES(authorized), authorized),
            captured   = IF(rev < VALUES(rev), VALUES(captured), captured),
            refunded   = IF(rev < VALUES(rev), VALUES(refunded), refunded),
            rev        = IF(rev < VALUES(rev), VALUES(rev), rev)'; // Update rev last to ensure the previous IFs work
        $this->db->query($q);
    }

}
