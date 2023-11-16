<?php

class ScanpayDb {
    private object $db;
    private int $shopid;
    private string $flock;

    public function __construct(object $db, int $shopid) {
        $this->db = $db;
        $this->shopid = $shopid;
    }

    public function lock(object $oc):void {
        //Simple filelock with mkdir (because it's atomic, fast and dirty!)
        try {
            $this->flock = sys_get_temp_dir() . '/scanpay_' . $this->shopid . '_lock';
            if (!@mkdir($this->flock) && file_exists($this->flock)) {
                $dtime = time() - filemtime($this->flock);
                if ($dtime > 0 && $dtime < 240) {
                    $oc->sendJson(['error' => 'busy'], 423);
                    die();
                } else {
                    $oc->log->write("Scanpay flock [$this->flock] exists (dtime=$dtime)");
                }
            }
        } catch (\Exception $e) {
            // Silence mkdir warnings (@ does not seem to work???)
            $oc->log->write("Scanpay flock [$this->flock] failed with: $e");
        }
    }

    public function unlock():void {
        @rmdir($this->flock);
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

    public function setMeta(int $orderid, array $change): void {
        $rev = (int)$change['rev'];
        $nacts = count($change['acts']);
        $this->db->query(
            "INSERT INTO " . DB_PREFIX . "scanpay_order
                SET
                    orderid = $orderid,
                    shopid = $this->shopid,
                    trnid = '" . (int)$change['id'] . "',
                    rev = $rev,
                    nacts = $nacts,
                    authorized = '" . $change['totals']['authorized'] . "',
                    captured = '" . $change['totals']['captured'] . "',
                    refunded = '" . $change['totals']['refunded'] . "',
                    voided = '" . $change['totals']['voided'] . "'
                ON DUPLICATE KEY UPDATE
                    rev = $rev,
                    nacts = $nacts,
                    authorized = '" . $change['totals']['authorized'] . "',
                    captured = '" . $change['totals']['captured'] . "',
                    refunded = '" . $change['totals']['refunded'] . "',
                    voided = '" . $change['totals']['voided'] . "'"
        );
    }

    public function getSeq(): array {
        $res = $this->db->query(
            "SELECT * FROM " . DB_PREFIX . "scanpay_seq
            WHERE shopid = $this->shopid LIMIT 1"
        );
        if ($res->num_rows === 0) {
            $this->db->query(
                "INSERT INTO " . DB_PREFIX . "scanpay_seq
                (shopid, seq, mtime)
                VALUES ($this->shopid, 0, 0)"
            );
            return ['seq' => 0, 'mtime' => 0];
        }
        return [
            'seq' => (int)$res->rows[0]['seq'],
            'mtime' => (int)$res->rows[0]['mtime']
        ];
    }

    public function setSeq(int $seq): void {
        $mtime = time();
        $this->db->query(
            "UPDATE " . DB_PREFIX . "scanpay_seq
            SET seq = $seq, mtime = $mtime
            WHERE shopid = $this->shopid"
        );
    }

    public function createTables() {
        // Delete old tables if they exist
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_seq");
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "scanpay_order");

        // Create new tables
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "scanpay_seq (
                shopid  INT unsigned NOT NULL UNIQUE,
                seq     INT unsigned NOT NULL,
                mtime   BIGINT unsigned NOT NULL,
                locked  TINYINT unsigned NOT NULL,
                PRIMARY KEY (shopid)
            ) CHARSET=latin1;"
        );
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
}
