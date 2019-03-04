<?php

namespace XTracer;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\base\Behavior;

use XTracer\Util;

class Span extends Behavior
{
    protected $info = [];
    protected $_refSpanID = null;

    public function __construct($traceID, $refSpanID, $name = null)
    {
        $this->_refSpanID = $refSpanID;

        $server_ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null;
        if (is_null($server_ip)) {
            $f = "/tmp/server_ip";
            if (!file_exists($f)) {
                @system('echo -n `ip route get 1.2.3.4 | awk \'{print $7}\'` > ' . $f);
            }
            $server_ip = @file_get_contents($f);
            if (empty($server_ip)) {
                $server_ip = '127.0.0.1';
            }
        }

        $now = microtime();
        list($a, $b) = explode(' ', microtime());
        $millisecond = intval($b) * 1000 + intval($a * 1000);
        $microsecond = intval($b) * 1000000 + intval($a * 1000000);

        $this->info = [
            'type'          => 'span',
            'traceID'       => $traceID,
            'spanID'        => Util::generateID(),
            'flags'         => 1, #不抽样
            'operationName' => $name,
            'references'    => [],
            'startTime'     => $millisecond,
            'startTimeMillis'   => $microsecond,
            'duration'      => -1,
            'tags'          => [],
            "logs"          => [],
            "process" => [
                "serviceName" => Yii::$app->name,
                "tags" => [
                    [
                        "key"   => "hostname",
                        "type"  => "string",
                        "value" => gethostname(),
                    ], [
                        "key"   => "ip",
                        "type"  => "string",
                        "value" => $server_ip,
                    ]
                ]
            ]
        ];

        if (is_null($traceID)) {
            $this->info['traceID'] = Util::generateID();
        } else {
            $this->info['references'] = [
                "refType"   => "CHILD_OF",
                "traceID"   => $traceID,
                "spanID"    => $refSpanID,
            ];
        }
    }

    public function httpSpan($method, $url, $isCaller = false)
    {
        $baseUrl = preg_replace('/\?.*$/', '', $url);

        if (is_null($this->info['operationName'])) {
            if ($isCaller) {
                $this->info['operationName'] = 'HTTP ' . $method . ': ' . $baseUrl;
            } else { #receiver
                $this->info['operationName'] = 'HTTP ' . $method . ' ' . $baseUrl;
            }
        }

        $this->info['tags'] = [
            [
                "key"   => "http.method",
                "type"  => "string",
                "value" => $method,
            ], [
                "key"   => "http.url",
                "type"  => "string",
                "value" => $url,
            ],
        ];

        $this->info['logs'] = [
            [
                "timestamp" => $this->info['startTimeMillis'],
                "fields" => [
                    [
                        "key"   => "http.method",
                        "type"  => "string",
                        "value" => $method,
                    ],
                    [
                        "key"   => "http.url",
                        "type"  => "string",
                        "value" => $url,
                    ],
                ],
            ],
        ];
    }

    public function rpcSpan($api)
    {
        if (is_null($this->info['operationName'])) {
            $this->info['operationName'] = 'RPC ' . $api;
        }
        #TODO
    }

    public function spanInfo()
    {
        return $this->info;
    }

    public function getTraceID()
    {
        return $this->info['traceID'];
    }

    public function getSpanID()
    {
        return $this->info['spanID'];
    }

    public function getRefSpanID()
    {
        return $this->_refSpanID;
    }

    public function logSpan($event)
    {
        #TODO
    }

    public function finishSpan()
    {
        $now = microtime();
        list($a, $b) = explode(' ', microtime());
        $millisecond = intval($b) * 1000 + intval($a * 1000);
        $microsecond = intval($b) * 1000000 + intval($a * 1000000);

        $this->info['duration'] = $microsecond - $this->info['startTimeMillis'];

        $this->info['tags'][] = [
            "key"   => "http.status_code",
            "type"  => "int64",
            "value" => Yii::$app->response->statusCode
        ];

        #TODO: log
        var_export($this->info);
        echo "\n";
    }

    public function subSpan()
    {
        return new Span($this->info['traceID'], $this->info['spanID']);
    }

}

