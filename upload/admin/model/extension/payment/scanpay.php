<?php

class ModelExtensionPaymentScanpay extends Model {
    public function install() {
        // Delete old databases (tmpfix)
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_seq");
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_order");

        /* Make seq table */
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "scanpay_seq (
                shopid  INT unsigned NOT NULL UNIQUE,
                seq     INT unsigned NOT NULL,
                mtime   BIGINT unsigned NOT NULL,
                locked  TINYINT unsigned NOT NULL,
                PRIMARY KEY (shopid)
            ) CHARSET=latin1;"
        );

        /* Make scanpay order table */
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "scanpay_order (
                orderid BIGINT unsigned NOT NULL UNIQUE,
                shopid INT unsigned NOT NULL,
                trnid INT unsigned NOT NULL,
                rev INT unsigned NOT NULL,
                nacts INT unsigned NOT NULL,
                authorized VARCHAR(64) NOT NULL,
                captured VARCHAR(64) NOT NULL,
                refunded VARCHAR(64) NOT NULL,
                voided VARCHAR(64) NOT NULL,
                PRIMARY KEY (orderid)
            ) CHARSET = latin1;"
        );
    }

    /*
        TODO: merge with catalog model. Maybe move into lib?
    */

    public function getOrderMeta(int $orderid, int $shopid): array {
        $sql = $this->db->query(
            "SELECT * FROM " . DB_PREFIX . "scanpay_order
            WHERE orderid = $orderid AND shopid = $shopid LIMIT 1"
        );
        if ($sql->num_rows === 0) {
            return [
                'shopid' => $shopid,
                'rev'   => 0,
                'nacts' => 0,
            ];
        }
        $row = $sql->rows[0];
        return [
            'orderid' => (int)$row['orderid'],
            'shopid' => (int)$row['shopid'],
            'trnid' => (int)$row['trnid'],
            'rev' => (int)$row['rev'],
            'nacts' => (int)$row['nacts'],
            'authorized' => (string)$row['authorized'],
            'captured' => (string)$row['captured'],
            'refunded' => (string)$row['refunded'],
            'voided' => (string)$row['voided'],
        ];
    }

    public function getSeq(int $shopid): array {
        $res = $this->db->query(
            "SELECT * FROM " . DB_PREFIX . "scanpay_seq 
            WHERE shopid = $shopid LIMIT 1"
        );
        if ($res->num_rows === 0) {
            $this->db->query(
                "INSERT INTO " . DB_PREFIX . "scanpay_seq 
                (shopid, seq, mtime)
                VALUES ($shopid, 0, 0)"
            );
            return ['seq' => 0, 'mtime' => 0];
        }
        return [ 
            'seq' => (int)$res->rows[0]['seq'], 
            'mtime' => (int)$res->rows[0]['mtime'] 
        ];
    }
}
