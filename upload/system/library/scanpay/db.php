<?php

function getScanpayOrder(object $db, int $orderid, int $shopid): array {
    $sql = $db->query(
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

function updateScanpayOrder(object $db, int $shopid, array $data): void {
    $rev = (int)$data['rev'];
    $nacts = count($data['acts']);
    $db->query(
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

function insertScanpayOrder(object $db, int $shopid, array $data): void {
    $db->query(
        "INSERT INTO " . DB_PREFIX . "scanpay_order 
        SET orderid = '" . (int)$data['orderid'] . "',
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

function getScanpaySeq(object $db, int $shopid): array {
    $res = $db->query(
        "SELECT * FROM " . DB_PREFIX . "scanpay_seq
        WHERE shopid = $shopid LIMIT 1"
    );
    if ($res->num_rows === 0) {
        $db->query(
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

function saveScanpaySeq(object $db, int $shopid, int $seq): void {
    $mtime = time();
    $db->query(
        "UPDATE " . DB_PREFIX . "scanpay_seq
        SET seq = $seq, mtime = $mtime
        WHERE shopid = $shopid"
    );
}

function createScanpayTables(object $db) {
    // Delete old tables if they exist
    $db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_seq");
    $db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_order");

    // Create new tables
    $db->query(
        "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "scanpay_seq (
            shopid  INT unsigned NOT NULL UNIQUE,
            seq     INT unsigned NOT NULL,
            mtime   BIGINT unsigned NOT NULL,
            locked  TINYINT unsigned NOT NULL,
            PRIMARY KEY (shopid)
        ) CHARSET=latin1;"
    );
    $db->query(
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
