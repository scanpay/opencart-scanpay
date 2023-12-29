<?php

/*
    Scanpay module client lib
    Version 2.2.0 (2023-12-29)
*/

class ScanpayClient
{
    private $ch; // CurlHandle class is added PHP 8.0
    private array $headers;
    private string $idemstatus;

    public function __construct(string $apikey)
    {
        if (!function_exists('curl_init')) {
            die("ERROR: Please enable php-curl\n");
        }
        $this->ch = curl_init();
        $this->headers = [
            'authorization' => 'Authorization: Basic ' . base64_encode($apikey),
            'x-shop-plugin' => 'X-Shop-Plugin: OC-EXTENSION_VERSION/' . VERSION . '; PHP-' . PHP_VERSION,
            'content-type' => 'Content-Type: application/json',
            'expect' => 'Expect: ',
        ];
        /* The 'Expect' header will disable libcurl's expect-logic,
            which will save us a HTTP roundtrip on POSTs >1024b. */
    }

    private function headerCallback(object $handle, string $header)
    {
        $arr = explode(':', $header, 2);
        if (isset($arr[1]) && strtolower(trim($arr[0])) === 'idempotency-status') {
            $this->idemstatus = strtolower(trim($arr[1]));
        }
        return strlen($header);
    }

    private function request(string $path, ?array $opts, ?array $data): array
    {
        $this->idemstatus = '';
        $curlopts = [
            CURLOPT_URL => 'https://api.scanpay.dk' . $path,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_DNS_CACHE_TIMEOUT => 180,
            //CURLOPT_DNS_SHUFFLE_ADDRESSES => 1,
        ];

        $headers = $this->headers;
        if (isset($opts, $opts['headers'])) {
            foreach ($opts['headers'] as $key => &$val) {
                $headers[strtolower($key)] = $key . ': ' . $val;
            }
            if (isset($headers['idempotency-key'])) {
                $curlopts[CURLOPT_HEADERFUNCTION] = [$this, 'headerCallback'];
            }
        }
        $curlopts[CURLOPT_HTTPHEADER] = array_values($headers);

        if (isset($data)) {
            $curlopts[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_SLASHES);
            if ($curlopts[CURLOPT_POSTFIELDS] === false) {
                throw new \Exception('Failed to JSON encode request to Scanpay: ' . json_last_error_msg());
            }
        }

        curl_reset($this->ch);
        curl_setopt_array($this->ch, $curlopts);
        $result = curl_exec($this->ch);
        if ($result === false) {
            throw new \Exception(curl_strerror(curl_errno($this->ch)));
        }

        $statusCode = (int) curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);
        if ($statusCode !== 200) {
            throw new \Exception((explode("\n", $result)[0]));
        }

        if (isset($opts, $headers['idempotency-key']) && $this->idemstatus !== 'ok') {
            throw new \Exception("Server failed to provide idempotency. Scanpay returned $statusCode - "
                . explode("\n", $result)[0]);
        }

        $json = json_decode($result, true);
        if (!is_array($json)) {
            throw new \Exception('Invalid JSON response from server');
        }
        return $json;
    }

    // newURL: Create a new payment link
    public function newURL(array $data, array $opts): string
    {
        $o = $this->request('/v1/new', $opts, $data);
        if (isset($o['url']) && filter_var($o['url'], FILTER_VALIDATE_URL)) {
            return $o['url'];
        }
        throw new \Exception('Invalid response from server');
    }

    // seq: Get array of changes since the reqested sequence number
    public function seq(int $num): array
    {
        $o = $this->request('/v1/seq/' . $num, null, null);
        if (isset($o['seq'], $o['changes']) && is_int($o['seq']) && is_array($o['changes'])) {
            $empty = empty($o['changes']);
            if (($empty && $o['seq'] <= $num) || (!$empty && $o['seq'] > $num)) {
                return $o;
            }
        }
        throw new \Exception('Invalid seq from server');
    }

    public function capture(int $trnid, array $data): array
    {
        return $this->request("/v1/transactions/$trnid/capture", null, $data);
    }

    public function charge(int $subid, array $data, array $opts = []): array
    {
        $o = $this->request("/v1/subscribers/$subid/charge", $opts, $data);
        if (
            isset($o['type']) && $o['type'] === 'charge' &&
            isset($o['id']) && is_int($o['id']) &&
            isset($o['totals']) && isset($o['totals']['authorized'])
        ) {
            return $o;
        }
        throw new \Exception('Invalid response from server');
    }

    public function renew(int $subid, array $data, array $opts = []): string
    {
        $o = $this->request("/v1/subscribers/$subid/renew", $opts, $data);
        if (isset($o['url']) && filter_var($o['url'], FILTER_VALIDATE_URL)) {
            return $o['url'];
        }
        throw new \Exception('Invalid response from server');
    }
}
