<?php

namespace XTracer;

class Util
{
    public static function generateID()
    {
        return sprintf("%08X%08X", crc32(rand()), crc32(microtime(true)));
    }
}
