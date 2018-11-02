<?php namespace Scanpay;

class Scanpay
{
    protected $_headers;
    protected $ch;
    protected $apikey;

    public function __construct($apikey = '')
    {
        // Check if libcurl is enabled
        if (!function_exists('curl_init')) { throw new \Exception('Enable php-curl.'); }

        // Public cURL handle (we want to reuse connections)
        $this->ch = curl_init();

        curl_setopt_array($this->ch, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 30, // Timeout after 30s
            CURLOPT_USE_SSL => CURLUSESSL_ALL,
        ));

        $this->_headers = array(
            'Authorization: Basic ' . base64_encode($apikey),
            'X-Shop-Plugin: opencart/0.32',
            'Content-Type: application/json',
        );

        $this->apikey = $apikey;
    }

    protected function request($url, $data, $opts)
    {
        $headers = $this->_headers;

        if (isset($opts)) {
            if (isset($opts['headers'])) {
                $headers = array_merge($headers, $opts['headers']);
            }
            if (isset($opts['auth'])) {  // redefine the API key.
                $headers[0] = 'Authorization: Basic ' . base64_encode($opts['auth']);
            }
        }

        if (isset($data)) {
            curl_setopt_array($this->ch, array(
                CURLOPT_URL => 'https://api.scanpay.dk' . $url,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
            ));
        } else {
            curl_setopt_array($this->ch, array(
                CURLOPT_URL => 'https://api.scanpay.dk' . $url,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_POSTFIELDS => 0,
            ));
        }

        if (!$result = curl_exec($this->ch)) {
            if ($errno = curl_errno($this->ch)) {
                if (function_exists('curl_strerror')) { // PHP 5.5
                    throw new \Exception(curl_strerror($errno));
                }
                throw new \Exception('curl_errno: ' . $errno);
            }
        }

        $code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        if ($code !== 200) {
            throw new \Exception(explode("\n", $result)[0]);
        }

        // Decode the json response (@: surpress warnings)
        if (!$resobj = @json_decode($result, true)) {
            throw new \Exception('Invalid response from server');
        }
        return $resobj;
    }

    public function newURL($data, $opts=null)
    {
        $o = $this->request('/v1/new', $data, $opts);
        if (isset($o['url']) && strlen($o['url']) > 10) {
            return $o;
        }
        throw new \Exception('Invalid response from server');
    }

    public function seq($seq, $opts=null)
    {
        $o = $this->request('/v1/seq/' . $seq, null, $opts);
        if (isset($o['seq']) && is_int($o['seq']) && isset($o['changes']) && is_array($o['changes'])) {
            return $o;
        }
        throw new \Exception('Invalid response from server');
    }

    public function handlePing($opts=[])
    {
        if (isset($opts['signature'])) {
            $sig = $opts['signature'];
        } else if (isset($_SERVER['HTTP_X_SIGNATURE'])) {
            $sig = $_SERVER['HTTP_X_SIGNATURE'];
        } else {
            throw new \Exception('missing ping signature');
        }

        $body = isset($opts['body']) ? $opts['body'] : file_get_contents('php://input');
        if ($body === false) {
            throw new \Exception('unable to get ping body');
        }

        $apikey = isset($opts['auth']) ? $opts['auth'] : $this->apikey;
        $mySig = base64_encode(hash_hmac('sha256', $body, $apikey, true));
        if (function_exists('hash_equals')) {
            if (!hash_equals($mySig, $sig)) {
                throw new \Exception('invalid ping signature');
            }
        } else if ($mySig !== $sig) {
            throw new \Exception('invalid ping signature');
        }

        $reqobj = @json_decode($body, true);
        if ($reqobj === null) {
            throw new \Exception('invalid json from Scanpay server');
        }

        if (!isset($reqobj['seq']) || !is_int($reqobj['seq']) ||
            !isset($reqobj['shopid']) || !is_int($reqobj['shopid']) ||
            $reqobj['seq'] < 0 || $reqobj['shopid'] < 0) {
            throw new \Exception('missing fields in Scanpay response');
        }

        return $reqobj;
    }
}

?>
