<?php

class ScanpayDb {
    private object $db;
    private int $shopid;

    public function __construct(object $db, int $shopid) {
        $this->db = $db;
        $this->shopid = $shopid;
    }

    public function getMeta(int $orderid): array {
        $sql = $this->db->query(
            "SELECT * FROM " . DB_PREFIX . "scanpay_order
            WHERE orderid = $orderid AND shopid = $this->shopid LIMIT 1"
        );
        if ($sql->num_rows === 0) {
            return ['shopid' => $this->shopid];
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

    public function getSeq(): array {
        $res = $this->db->query(
            "SELECT * FROM " . DB_PREFIX . "scanpay_seq
            WHERE shopid = $this->shopid LIMIT 1"
        );
        if ($res->num_rows === 0) {
            $this->db->query(
                "INSERT INTO " . DB_PREFIX . "scanpay_seq
                (shopid, seq, ping, mtime)
                VALUES ($this->shopid, 0, 0, 0)"
            );
            return ['seq' => 0, 'mtime' => 0];
        }
        return [
            'seq' => (int)$res->rows[0]['seq'],
            'ping' => (int)$res->rows[0]['ping'],
            'mtime' => (int)$res->rows[0]['mtime']
        ];
    }

    public function setSeq(int $seq): void {
        $mtime = time();
        $this->db->query(
            "UPDATE " . DB_PREFIX . "scanpay_seq
            SET mtime = $mtime, seq = $seq
            WHERE shopid = $this->shopid"
        );
    }

    public function savePing(array $ping): void {
        $mtime = time();
        $pingSeq = (int)$ping['seq'];
        $this->db->query(
            "UPDATE " . DB_PREFIX . "scanpay_seq
            SET mtime = $mtime, ping = $pingSeq
            WHERE shopid = $this->shopid"
        );
    }
}
