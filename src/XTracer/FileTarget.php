<?php

namespace XTracer;

use Yii;
use yii\log\Logger;
use yii\helpers\FileHelper;
use yii\helpers\VarDumper;

use XTracer\Tracer;

class FileTarget extends \yii\log\FileTarget
{
    # Improve performance on linux.
    public $rotateByCopy = false;

    public function formatMessage($message)
    {
        list($text, $level, $category, $timestamp) = $message;

        if ($category == Tracer::CATEGORY) {
            return $text;
        }

        $level = Logger::getLevelName($level);
        if (!is_string($text)) {
            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($text instanceof \Throwable || $text instanceof \Exception) {
                $text = (string) $text;
            } else {
                $text = VarDumper::export($text);
            }
        }

        $traces = [];
        if (isset($message[4])) {
            foreach ($message[4] as $trace) {
                $traces[] = "[{$trace['file']}:{$trace['line']}]";
            }
        }

        $fields = [
            ['stack', 'string', (empty($traces) ? '' : implode("\n", $traces))],
            ['level', 'string', $level],
            ['category', 'string', $category],
            ['message', 'string', $text],
        ];

        return Tracer::getSpan()->getLogJson($fields);
    }
}
