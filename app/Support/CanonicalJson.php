<?php

namespace App\Support;

class CanonicalJson
{
    public static function hash(array $value): string
    {
        self::sort($value);
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function sort(array &$value): void
    {
        foreach ($value as &$item) if (is_array($item)) self::sort($item);
        if (! array_is_list($value)) ksort($value);
    }
}
