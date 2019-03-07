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
    const CATEGORY = 'jaeger';

    public static $span = null;

    public $maskRules = [];
    public $maskMethod = null;

    public static function getSpan()
    {
        if (is_null(self::$span)) {
            self::$span = new Span(null, null, 'ERROR');
        }
        return self::$span;
    }

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
        ]);
    }

    public static function afterWebAction($event)
    {
        $code = Yii::$app->response->statusCode;
        self::$span->addTag('http.status_code', 'int64', strval($code));
        Yii::info(self::$span->getLogJson([['stage', 'string', 'afterWebAction']]), self::CATEGORY);
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
        Yii::info(self::$span->getLogJson([['stage', 'string', 'afterConsoleAction']]), self::CATEGORY);
    }

    public static function test()
    {
        return "This is [" . __METHOD__ . "]";
    }
}
