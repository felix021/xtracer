<?php

namespace XTracer;

use Yii;

use XTracer\Mask;

class Util
{
    public static function generateID()
    {
        return sprintf("%08x%08x", crc32(rand()), crc32(microtime()));
    }

    public static function safeUrl($url)
    {
        $fields = parse_url($url);
        $path = $fields['path'];
        if (!array_key_exists('query', $fields)) {
            return $path;
        }

        parse_str($fields['query'], $query);
        $query = Mask::apply($query);
        return $path . '?' .  str_replace('%2A', '*', http_build_query($query));
    }
}
