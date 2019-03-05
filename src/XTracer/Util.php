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

        $scheme = isset($fields['scheme']) ? ($fields['scheme'] . '://') : '';

        $userpass = (isset($fields['user']) and isset($fields['pass'])) ? sprintf("%s:%s@", $fields['user'], $fields['pass']) : '';

        $host = isset($fields['host']) ? $fields['host'] : '';

        $port = isset($fields['port']) ? ':' . $fields['port'] : '';
        if ($scheme . $port == 'http://:80' or $scheme . $port == 'https://:443') {
            $port = '';
        }

        $path = isset($fields['path']) ? $fields['path'] : '';

        $query = isset($fields['query']) ? $fields['query'] : '';
        parse_str($query, $arr_query);
        $query = str_replace('%2A', '*', http_build_query(Mask::apply($arr_query)));
        if (strlen($query) > 0) {
            $query = '?' . $query;
        }

        return sprintf("%s%s%s%s%s%s", $scheme, $userpass, $host, $port, $path, $query);
    }
}
