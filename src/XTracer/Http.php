<?php

namespace XTracer;

use Yii;

use XTracer\Tracer;
use XTracer\Span;

class Http
{
    const CODE_OK = 200;
    const CODE_UNKNOWN = 520;

    protected $span = null;

    public $ch = null;

    protected $timeout = 30; #seconds

    public function __construct($span = null)
    {
        if (is_null($span)) {
            $this->span = Tracer::$span;
        }
        $this->ch = curl_init();
    }

    protected function fixUrl($method, $url, $data)
    {
        if (in_array($method, ['GET', 'HEAD'])) {
            $sep = (strpos($url, '?') === false) ? '?' : '&';
            if (is_array($data)) {
                $data = http_build_query($data);
            }
            if (strlen($data) > 0) {
                $url .= $sep . $data;
            }
        }

        return $url;
    }

    public function get($url, $data = [], $headers = [], $options = [])
    {
        return $this->call('GET', $url, $data, $headers, $options);
    }

    public function post($url, $data = [], $headers = [], $options = [])
    {
        return $this->call('POST', $url, $data, $headers, $options);
    }

    public function call($method, $url, $data = [], $headers = [], $options = [])
    {
        $ch = $this->ch;

        $outbound = new \XTracer\Outbound($this->span);
        $outbound->beginHttp($method, $url, Span::IS_CALLER);

        if (!is_array($headers)) {
            throw new \Exception("invalid headers");
        }
        $headers[] = $outbound->getTraceKey() . ': ' . $outbound->getTraceValue();

        $default_options = [
            CURLOPT_URL             => $this->fixUrl($method, $url, $data),
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 5,
            CURLOPT_TIMEOUT         => $this->timeout,
            CURLOPT_HEADER          => true,
            CURLOPT_HTTPHEADER      => $headers,
        ];

        switch ($method) {
        case "GET" :
            $default_options[CURLOPT_HTTPGET] = true;
            break;

        case "HEAD" :
            $default_options[CURLOPT_CUSTOMREQUEST] = 'HEAD';
            break;

        case "POST":
            $default_options[CURLOPT_POST] = true;
            $default_options[CURLOPT_POSTFIELDS] = $data;
            break;

        case "PUT" :
            $default_options[CURLOPT_CUSTOMREQUEST] = "PUT";
            $default_options[CURLOPT_POSTFIELDS] = $data;
            break;

        case "DELETE":
            $default_options[CURLOPT_CUSTOMREQUEST] = "DELETE";
            $default_options[CURLOPT_POSTFIELDS] = $data;
            break;

        default:
            throw new \Exception("invalid method: " . $method);
        }

        unset($options[CURLOPT_HTTPHEADER]);
        foreach ($options as $optkey => $optval) {
            $default_options[$optkey] = $optval;
        }
        curl_setopt_array($ch, $default_options);

        $response = curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            $result = [
                'errno'     => curl_errno($ch),
                'code'      => self::CODE_UNKNOWN,
                'message'   => curl_error($ch),
            ];
        } else {
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            if ($default_options[CURLOPT_HEADER] != true) {
                $header_size = 0;
            }

            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $result = $this->parseHeader($header);
            $result['errno'] = 0;
            $result['body'] = $body;
            #$result['header'] = $header;
        }

        $outbound->addTag('http.status_code', 'int64', $result['code']);
        $outbound->addTag('http.message', 'string', $result['message']);
        $outbound->finish();

        curl_close($ch);
        return $result;
    }

    public function parseHeader($header)
    {
        $header = str_replace("\r\n", "\n", trim($header));
        $arr_headers = explode("\n\n", $header);
        $headers = explode("\n", $arr_headers[count($arr_headers) - 1]);

        list($protocol, $code, $message) = explode(" ", $headers[0], 3);

        $fields = [];
        for ($i = 1; $i < count($headers); $i += 1) {
            $line = trim($headers[$i]);
            if ($line == "") {
                continue;
            }
            $kv = explode(":", $line, 2);
            if (count($kv) != 2) {
                $fields[$key][] = "";
                continue;
            } else {
                list($key, $value) = $kv;
                $fields[$key][] = trim($value);
            }
        }

        foreach ($fields as $key => $value) {
            if (is_array($value) and count($value) == 1) {
                $fields[$key] = $value[0];
            }
        }

        return [
            'protocol'  => $protocol,
            'code'      => intval($code),
            'message'   => $message,
            'headers'   => $fields,
        ];
    }
}
