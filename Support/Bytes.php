<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Support;

final class Bytes
{
    public static function human(int|false $bytes): string
    {
        $bytes = (int) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
