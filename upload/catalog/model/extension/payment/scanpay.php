<?php

class ModelExtensionPaymentScanpay extends Model {
    public function getMethod($address, $total): array {
        $this->language->load('extension/payment/scanpay');
        return [
            'code'       => 'scanpay',
            'title'      => $this->language->get('text_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_scanpay_sort_order'),
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

    public function saveSeq(int $shopid, int $seq): void {
        $mtime = time();
        $this->db->query(
            "UPDATE " . DB_PREFIX . "scanpay_seq
            SET seq = $seq, mtime = $mtime
            WHERE shopid = $shopid"
        );
    }

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

    public function updateOrderMeta(int $shopid, array $data): void {
        $rev = (int)$data['rev'];
        $nacts = count($data['acts']);
        $this->db->query(
            "UPDATE " . DB_PREFIX . "scanpay_order
            SET rev = '$rev',
                nacts = '$nacts',
                authorized = '" . $data['totals']['authorized'] . "',
                captured = '" . $data['totals']['captured'] . "',
                refunded = '" . $data['totals']['refunded'] . "',
                voided = '" . $data['totals']['voided'] . "'
            WHERE orderid = $data[orderid] AND shopid = $shopid"
        );
    }

    public function insertOrderMeta(int $shopid, array $data): void {
        $this->db->query(
            "INSERT INTO " . DB_PREFIX . "scanpay_order 
            SET orderid = '" . (int)$data['orderid'] ."',
                shopid = '$shopid',
                trnid = '" . (int)$data['id'] . "',
                rev = '" . (int)$data['rev'] . "',
                nacts = '" . count($data['acts']) . "',
                authorized = '" . $data['totals']['authorized'] . "',
                captured = '" . $data['totals']['captured'] . "',
                refunded = '" . $data['totals']['refunded'] . "',
                voided = '" . $data['totals']['voided'] . "'"
        );
    }
}
