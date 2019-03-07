<?php

namespace XTracer;

use Yii;
use yii\web\Controller;
use yii\web\Response;

use XTracer\Util;

class Span
{
    const IS_CALLER = true;

    protected $info = [];

    protected $type = null;

    public function __construct($traceID, $refSpanID, $name = null)
    {
        if (is_null($traceID)) {
            $traceID = Util::generateID();
            $spanID = $traceID;
            $ref = [];
        } else {
            $spanID = Util::generateID();
            $ref = [[
                "refType"   => "CHILD_OF",
                "traceID"   => $traceID,
                "spanID"    => $refSpanID,
            ]];
        }

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
            'spanID'        => $spanID,
            'flags'         => 1, #不抽样
            'operationName' => $name,
            'references'    => $ref,
            'startTime'     => $microsecond,
            'startTimeMillis'   => $millisecond,
            'duration'      => -1,
            'tags'          => [],
            "logs"          => [],
            "process" => [
                "serviceName" => Yii::$app->name,
                "tags" => [
                    [
                        "key"   => "jaeger.version",
                        "type"  => "string",
                        "value" => "php-xtracer",
                    ], [
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
    }

    public function httpSpan($method, $url, $isCaller = false)
    {
        $safeUrl = Util::safeUrl($url);
        $fields = parse_url($safeUrl);
        $path = $fields['path'];

        if (is_null($this->getName())) {
            if ($isCaller) {
                $name = 'HTTP ' . $method . ': ' . $path;
            } else { #receiver
                $name = 'HTTP ' . $method . ' ' . $path;
            }
            $this->setName($name);
        }

        $this->addTag('http.method', 'string', $method);
        $this->addTag('http.url', 'string', $safeUrl);

        $this->addLog([
            ["http.method", "string", $method],
            ["http.url", "string", $safeUrl],
        ], $this->info['startTime']);
    }

    public function cmdSpan($action, $controller, $params)
    {
        $this->type = 'cmd';

        $this->info['operationName'] = sprintf('CMD %s %s', $controller, $action);

        $this->addTag('cmd.controller', 'string', $controller);
        $this->addTag('cmd.action', 'string', $action);
        $this->addTag('cmd.params', 'string', json_encode($params));

        $this->addLog([
            ['cmd.controller', 'string', $controller],
            ['cmd.action', 'string', $controller],
        ], $this->info['startTime']);
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

    public function getLog($fields = [], $tags = [])
    {
        $now = microtime();
        list($a, $b) = explode(' ', microtime());
        $millisecond = intval($b) * 1000 + intval($a * 1000);
        $microsecond = intval($b) * 1000000 + intval($a * 1000000);

        $info = $this->info;

        if (is_null($info['operationName'])) {
            $info['operationName'] = '<UNKNOWN>';
        }

        $info['duration'] = $microsecond - $info['startTime'];

        $this->addLog($fields, $microsecond);

        foreach ($tags as $tag) {
            list($key, $type, $value) = $tag;
            $info['tags'] = [
                "key"   => $key,
                "type"  => $type,
                "value" => $value,
            ];
        }

        $info['logTime'] = date('Y-m-d H:i:s.', $b) . intval($a * 1000);

        return $info;
    }

    public function getLogJson($fields = [], $tags = [])
    {
        return json_encode($this->getLog($fields, $tags, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function addTag($key, $type, $value)
    {
        $this->info['tags'][] = [
            "key"   => $key,
            "type"  => $type,
            "value" => $value,
        ];
    }

    public function addLog($fields, $timestamp = null)
    {
        if (is_null($timestamp)) {
            $now = microtime();
            list($a, $b) = explode(' ', microtime());
            $timestamp = intval($b) * 1000000 + intval($a * 1000000); #microsecond
        }
        $log['timestamp'] = $timestamp;
        foreach ($fields as $field) {
            list($key, $type, $value) = $field;
            $log['fields'][] = [
                "key"   => $key,
                "type"  => $type,
                "value" => $value,
            ];
        }
        $this->info['logs'][] = $log;
    }

    public function subSpan()
    {
        return new Span($this->info['traceID'], $this->info['spanID']);
    }

    public function getName()
    {
        return $this->info['operationName'];
    }

    public function setName($name)
    {
        $this->info['operationName'] = $name;
        return $this;
    }
}
