<?php

namespace XTracer;

use Yii;

use XTracer\Tracer;
use XTracer\Span;

class Outbound
{
    protected $parent_span = null;
    protected $span = null;

    public function __construct($current_span = null)
    {
        if (is_null($current_span)) {
            $this->parent_span = Tracer::$span;
        } else {
            $this->parent_span = $current_span;
        }

        $this->span = $this->parent_span->subSpan();
        $this->span->addTag('request.role', 'string', 'Invoker');
    }

    public function beginHttp($method, $url, $isCaller = false)
    {
        $this->span->httpSpan($method, $url, $isCaller);
    }

    public function getTraceKey()
    {
        return 'uber-trace-id';
    }

    public function getTraceValue()
    {
        return sprintf("%s:%s:%s:1", $this->span->getTraceID(), $this->span->getSpanID(), $this->parent_span->getSpanID());
    }

    public function addTag($key, $type, $value)
    {
        $this->span->addTag($key, $type, $value);
    }

    public function finish()
    {
        Yii::info($this->span->getLogJson([['stage', 'string', __METHOD__]]), Tracer::CATEGORY);
    }
}
