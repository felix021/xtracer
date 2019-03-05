<?php

namespace XTracer;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\base\Component;
use yii\log\Logger;

use XTracer\Span;
use XTracer\Util;

class Tracer extends Component
{
    public static $span = null;

    public $maskRules = [];
    public $maskMethod = null;

    public static function beforeAction($event)
    {
        if (Yii::$app instanceof yii\console\Application) {
            self::beforeConsoleAction($event);
        } else {
            self::beforeWebAction($event);
        }
    }

    public static function afterAction($event)
    {
        if (Yii::$app instanceof yii\console\Application) {
            self::afterConsoleAction($event);
        } else {
            self::afterWebAction($event);
        }
    }

    public static function beforeWebAction($event)
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

        self::$span = $span;

        $server = $_SERVER;
        unset($server['REQUEST_URI']);
        unset($server['QUERY_STRING']);

        Yii::info([
            '$_GET' => Mask::apply($_GET),
            '$_POST' => Mask::apply($_POST),
            '$_SERVER' => $server,
        ], 'beforeConsoleAction');
    }

    public static function afterWebAction($event)
    {
        $code = Yii::$app->response->statusCode;
        self::$span->addTag('http.status_code', 'int64', $code);
        Yii::info('afterWebAction', 'jaeger');
    }

    public static function beforeConsoleAction($event)
    {
        $action = $event->action;
        $controller = $action->controller;
        $span = new Span(null, null);
        $span->cmdSpan($action->id, $controller->id, Yii::$app->request->params);
        self::$span = $span;
    }

    public static function afterConsoleAction($event)
    {
        self::$span->addTag('cmd.result', 'string', json_encode($event->result));
        Yii::info('afterConsoleAction', 'jaeger');
    }

    public static function curl($url, $method = 'GET', $data = null, $options = [])
    {
        $span = self::$span->subSpan();

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
        curl_setopt($ch, CURLOPT_HEADER, "uber-trace-id: " . $span->getTrace());

        foreach ($options as $opt) {
            if (count($opt) != 2) {
                throw new \Exception("Invalid opt: should be [option, value]");
            }
            curl_setopt($ch, $opt[0], $opt[1]);
        }

        $result = curl_exec($ch);
        Yii::info(self::$span->getLogJson(), 'jaeger');
    }

    public static function test()
    {
        return "This is [" . __METHOD__ . "]";
    }
}
