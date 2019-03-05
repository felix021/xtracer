<?php

namespace XTracer;

use Yii;

class Mask
{
    public static function apply($data)
    {
        $tracer = Yii::$app->tracer;
        if (!$tracer) {
            return $data;
        }
        $data = self::applyRules($data, $tracer->maskRules);
        $data = self::applyMethod($data, $tracer->maskMethod);
        return $data;
    }

    public static function applyMethod($data, $method)
    {
        if (is_callable($method)) {
            $data = call_user_func_array($method, $data);
        }
        return $data;
    }


    public static function applyRules($data, $rules)
    {
        foreach ($rules as $rule) {
            if (count($rule) < 3) {
                throw new \Exception("invalid mask rule: " . json_encode($rule));
            }
            list($key, $method, $args) = $rule;
            if (!array_key_exists($key, $data)) {
                continue;
            }
            if ($method == 'unset') {
                unset($data[$key]);
                continue;
            }
            array_unshift($args, $data[$key]);
            if (is_callable($method)) {
                $data[$key] = call_user_func_array($method, $args);
                continue;
            }
            $selfmethod = 'self::mask' . ucfirst($method);
            if (is_callable($selfmethod)) {
                $data[$key] = call_user_func_array($selfmethod, $args);
                continue;
            }
            throw new \Exception("invalid mask method: " . $method);
        }

        return $data;
    }

    public static function maskAll($val, $len = 8)
    {
        return str_pad('', $len, '*');
    }

    public static function dupchar($char, $n)
    {
        return str_pad('', $n, $char);
    }

    public static function maskPrefix($val, $len)
    {
        $valLen = mb_strlen($val, 'utf-8');
        if ($len >= 0) {
            $prefix = mb_substr($val, 0, $len, 'utf-8');
            return $prefix . self::dupchar('*', $valLen - $len);
        } else {
            $suffix = mb_substr($val, -$len, NULL, 'utf-8');
            return self::dupchar('*', -$len) . $suffix;
        }
    }

    public static function maskSuffix($val, $len)
    {
        $valLen = mb_strlen($val, 'utf-8');
        if ($len >= 0) {
            $suffix = mb_substr($val, -$len, NULL, 'utf-8');
            return self::dupchar('*', $valLen - $len) . $suffix;
        } else {
            $prefix = mb_substr($val, 0, $len, 'utf-8');
            return $prefix . self::dupchar('*', -$len);
        }
    }

    public static function maskPrefixSuffix($val, $prefixLen, $suffixLen)
    {
        $valLen = mb_strlen($val, 'utf-8');
        if ($prefixLen >= 0 and $suffixLen >= 0) {
            $prefix = mb_substr($val, 0, $prefixLen, 'utf-8');
            $suffix = mb_substr($val, -$suffixLen, NULL, 'utf-8');
            return $prefix . self::dupchar('*', $valLen - $prefixLen - $suffixLen) . $suffix;
        } elseif ($prefixLen < 0 and $suffixLen < 0) {
            $prefixLen = -$prefixLen;
            $suffixLen = -$suffixLen;
            $middle = mb_substr($val, $prefixLen, -$suffixLen, 'utf-8');
            return self::dupchar('*', $prefixLen) . $middle . self::dupchar('*', $suffixLen);
        } else {
            throw new \Exception("invalid length");
        }
    }
}
