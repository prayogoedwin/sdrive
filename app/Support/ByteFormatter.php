<?php

namespace App\Support;

class ByteFormatter
{
    public static function human(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'TB') {
                $decimals = $value >= 10 ? 0 : 1;

                return number_format($value, $decimals, ',', '.').' '.$unit;
            }

            $value /= 1024;
        }

        return $bytes.' B';
    }
}
