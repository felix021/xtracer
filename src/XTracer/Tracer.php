<?php

namespace XTracer;

use Yii;
use yii\web\Controller;
use yii\web\Response;

use XTracer\Span;

class Tracer
{
    static $request = [];

    public static function beforeRequest($event)
    {
        $trace = Yii::$app->request->headers->get('uber-trace-id', '');
        $fields = explode(":", $trace);
        if (count($fields) == 4) {
            list($traceID, $spanID, $parentSpanID, $flags) = $fields;
        } else {
            $traceID = null;
            $spanID = null;
        }

        $method = Yii::$app->request->method;
        $url = Yii::$app->request->url;

        $span = new Span($traceID, $spanID);
        $span->httpSpan($method, $url);

        Yii::$app->request->attachBehavior('span', $span);
    }

    public static function afterRequest($event)
    {
        Yii::$app->request->finishSpan();
    }

    public static function curl($url, $method = 'GET', $data = null, $options = [])
    {
        $span = Yii::$app->request->subSpan();

        $trace = sprintf("%s:%s:%s:1", $span->traceID, $span->spanID, $span->refSpanID);

        $ch = curl_init();

        if (in_array($method, ['GET', 'HEAD'])) {
            $sep = (strpos($url, '?') === false) ? '?' : '&';
            if (is_array($data)) {
                $data = http_build_query($data);
            }
            $url .= $sep . $data;
        }
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, "uber-trace-id: " . $trace);

        foreach ($options as $opt) {
            if (count($opt) != 2) {
                throw new Exception("Invalid opt: should be [option, value]");
            }
            curl_setopt($ch, $opt[0], $opt[1]);
        }

        $result = curl_exec($ch);
        $span->finish();
    }

    public static function test()
    {
        return "This is [" . __METHOD__ . "]";
    }
}
