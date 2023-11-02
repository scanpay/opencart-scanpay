<?php

namespace Scanpay;

// phpcs:ignoreFile

class IdempotentResponseException extends \Exception
{
}

class Scanpay
{
    private $ch; // CurlHandle class is added PHP 8.0
    private array $headers;
    private string $apikey;
    private string $idemstatus;
    private array $opts;

    public function __construct(?string $apikey = '', array $opts = [])
    {
        if (!function_exists('curl_init')) {
            die("ERROR: Please enable php-curl\n");
        }
        $this->ch = curl_init(); // reuse handle
        $this->apikey = $apikey ?? '';
        $this->opts = $opts;
        $this->headers = [
            'authorization' => 'Authorization: Basic ' . base64_encode($this->apikey),
            'x-sdk' => 'X-SDK: PHP-2.1.1/' . PHP_VERSION,
            'x-shop-plugin' => 'X-Shop-Plugin: opencart/' . VERSION . '/' . SCANPAY_VERSION,
            'content-type' => 'Content-Type: application/json',
            'expect' => 'Expect: ',
        ];
        /* The 'Expect' header will disable libcurl's expect-logic,
            which will save us a HTTP roundtrip on POSTs >1024b. */

        if (isset($opts['headers'])) {
            foreach ($opts['headers'] as $key => &$val) {
                $this->headers[strtolower($key)] = $key . ': ' . $val;
            }
        }
    }

    private function headerCallback(object $handle, string $header)
    {
        $arr = explode(':', $header, 2);
        if (isset($arr[1]) && strtolower(trim($arr[0])) === 'idempotency-status') {
            $this->idemstatus = strtolower(trim($arr[1]));
        }
        return strlen($header);
    }

    private function request(string $path, array $opts = [], array $data = null): array
    {
        $this->idemstatus = '';
        $headers = $this->headers;
        if (isset($opts['headers'])) {
            foreach ($opts['headers'] as $key => &$val) {
                $headers[strtolower($key)] = $key . ': ' . $val;
            }
        }
        $opts = array_merge($this->opts, $opts);

        $curlopts = [
            CURLOPT_URL => 'https://' . ($opts['hostname'] ?? 'api.scanpay.dev') . $path,
            CURLOPT_HTTPHEADER => array_values($headers),
            CURLOPT_VERBOSE => $opts['debug'] ?? 0,
            CURLOPT_TCP_KEEPALIVE => 1, // TODO: CURLOPT_TCP_KEEPINTVL & CURLOPT_TCP_KEEPIDLE
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_DNS_CACHE_TIMEOUT => 180,
            //CURLOPT_DNS_SHUFFLE_ADDRESSES => 1,
        ];
        if (isset($data)) {
            $curlopts[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_SLASHES);
            if ($curlopts[CURLOPT_POSTFIELDS] === false) {
                throw new \Exception('Failed to JSON encode request to Scanpay: ' . json_last_error_msg());
            }
        }
        if (isset($headers['idempotency-key'])) {
            $curlopts[CURLOPT_HEADERFUNCTION] = [$this, 'headerCallback'];
        }

        if (isset($opts['curl'])) {
            foreach ($opts['curl'] as $key => &$val) {
                $curlopts[$key] = $val;
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
            throw new \Exception(('Scanpay returned "' . explode("\n", $result)[0] . '"'));
        }

        if (isset($headers['idempotency-key']) && $this->idemstatus !== 'ok') {
            throw new \Exception("Server failed to provide idempotency. Scanpay returned $statusCode - "
                . explode("\n", $result)[0]);
        }

        $json = json_decode($result, true);
        if (!is_array($json)) {
            throw new IdempotentResponseException('Invalid JSON response from server');
        }
        return $json;
    }

    // newURL: Create a new payment link
    public function newURL(array $data, array $opts = []): string
    {
        $o = $this->request('/v1/new', $opts, $data);
        if (isset($o['url']) && filter_var($o['url'], FILTER_VALIDATE_URL)) {
            return $o['url'];
        }
        throw new \Exception('Invalid response from server');
    }

    // seq: Get array of changes since the reqested seqnum
    public function seq(int $seqnum, array $opts = []): array
    {
        $o = $this->request('/v1/seq/' . $seqnum, $opts);
        if (
            isset($o['seq']) && is_int($o['seq']) &&
            isset($o['changes']) && is_array($o['changes'])
        ) {
            return $o;
        }
        throw new \Exception('Invalid response from server');
    }

    public function parsePing(string $body, string $signature): array
    {
        $checksum = base64_encode(hash_hmac('sha256', $body, $this->apikey, true));
        if (hash_equals($checksum, $signature)) {
            $o = json_decode($body, true);
            if ($o !== null && isset($o['seq']) && is_int($o['seq']) && isset($o['shopid'])) {
                return $o;
            }
        }
        throw new \Exception('invalid ping');
    }

    // handlePing: Convert data to JSON and validate integrity
    public function handlePing(array $opts = []): array
    {
        if (isset($opts['signature'])) {
            $signature = $opts['signature'];
        } elseif (isset($_SERVER['HTTP_X_SIGNATURE'])) {
            $signature = $_SERVER['HTTP_X_SIGNATURE'];
        } else {
            throw new \Exception('missing ping signature');
        }
        $body = isset($opts['body']) ? $opts['body'] : file_get_contents('php://input');
        if ($body === false) {
            throw new \Exception('unable to get ping body');
        }
        return $this->parsePing($body, $signature);
    }

    public function capture(int $trnid, array $data, array $opts = []): array
    {
        return $this->request("/v1/transactions/$trnid/capture", $opts, $data);
    }

    public function generateIdempotencyKey(): string
    {
        return rtrim(base64_encode(random_bytes(32)), '=');
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
