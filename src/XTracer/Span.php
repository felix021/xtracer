<?php

namespace XTracer;

use Yii;
use yii\web\Controller;
use yii\web\Response;

use XTracer\Util;

class Span
{
    protected $info = [];
    protected $_refSpanID = null;

    protected $type = null;

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
        $this->type = 'http';

        $baseUrl = preg_replace('/\?.*$/', '', $url);

        if (is_null($this->info['operationName'])) {
            if ($isCaller) {
                $this->info['operationName'] = 'HTTP ' . $method . ': ' . $baseUrl;
            } else { #receiver
                $this->info['operationName'] = 'HTTP ' . $method . ' ' . $baseUrl;
            }
        }

        $this->addTag('http.method', 'string', $method);
        $this->addTag('http.url', 'string', $url);

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

    public function cmdSpan($action, $controller, $params)
    {
        $this->type = 'cmd';

        $this->info['operationName'] = sprintf('CMD %s %s', $controller, $action);

        $this->addTag('cmd.controller', 'string', $controller);
        $this->addTag('cmd.action', 'string', $action);
        $this->addTag('cmd.params', 'string', json_encode($params));

        $this->info['logs'] = [
            [
                "timestamp" => $this->info['startTimeMillis'],
                "fields" => [
                    [
                        "key"   => "cmd.controller",
                        "type"  => "string",
                        "value" => $controller,
                    ],
                    [
                        "key"   => "cmd.action",
                        "type"  => "string",
                        "value" => $action,
                    ],
                ],
            ],
        ];
    }


    public function rpcSpan($api)
    {
        $this->type = 'rpc';

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

    public function log($event)
    {
        #TODO
    }

    public function addTag($key, $type, $value)
    {
        $this->info['tags'][] = [
            "key"   => $key,
            "type"  => $type,
            "value" => $value,
        ];
    }

    public function addLogFields($key, $type, $value)
    {
        $this->info['logs']['fields'][] = [
            "key"   => $key,
            "type"  => $type,
            "value" => $value,
        ];
    }

    public function finish()
    {
        $now = microtime();
        list($a, $b) = explode(' ', microtime());
        $millisecond = intval($b) * 1000 + intval($a * 1000);
        $microsecond = intval($b) * 1000000 + intval($a * 1000000);

        $this->info['duration'] = $microsecond - $this->info['startTimeMillis'];

        #TODO: log
        var_export($this->info);
        echo "\n";
    }

    public function subSpan()
    {
        return new Span($this->info['traceID'], $this->info['spanID']);
    }
}

