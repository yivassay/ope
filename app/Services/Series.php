<?php
declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class Series
{
    /**
     * @return array<int,string> list of dates YYYY-MM-DD inclusive
     */
    public static function dateRange(string $from, string $to, DateTimeZone $tz): array
    {
        $start = new DateTimeImmutable($from . ' 00:00:00', $tz);
        $end = new DateTimeImmutable($to . ' 00:00:00', $tz);
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }
        $out = [];
        $cur = $start;
        while ($cur <= $end) {
            $out[] = $cur->format('Y-m-d');
            $cur = $cur->add(new DateInterval('P1D'));
        }
        return $out;
    }

    /**
     * @param array<int,string> $dates
     * @param array<string,mixed> $byDate row map keyed by date
     */
    public static function fill(array $dates, array $byDate, string $field, float $default = 0.0): array
    {
        $out = [];
        foreach ($dates as $d) {
            if (!isset($byDate[$d])) {
                $out[] = $default;
                continue;
            }
            $v = $byDate[$d][$field] ?? $default;
            $out[] = is_numeric($v) ? (float)$v : $default;
        }
        return $out;
    }
}

