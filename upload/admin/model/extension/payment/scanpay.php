<?php

class ModelExtensionPaymentScanpay extends Model {

    public function install() {
        /* Make seq table */
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "scanpay_seq` (
              `shopid` BIGINT UNSIGNED NOT NULL UNIQUE,
              `seq`    BIGINT UNSIGNED NOT NULL,
              `mtime`  BIGINT NOT NULL,
              PRIMARY KEY (`shopid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        /* Make scanpay order table */
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "scanpay_order` (
              `orderid`    BIGINT UNSIGNED NOT NULL UNIQUE,
              `shopid`     BIGINT UNSIGNED NOT NULL,
              `trnid`      BIGINT UNSIGNED NOT NULL,
              `rev`        BIGINT NOT NULL,
              `nacts`      BIGINT NOT NULL,
              `authorized` DECIMAL(15,4) NOT NULL,
              `captured`   DECIMAL(15,4) NOT NULL,
              `refunded`   DECIMAL(15,4) NOT NULL,
              PRIMARY KEY (`orderid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
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

}
