<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class Format
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public static function date(mixed $d, bool $withYear = true): string
    {
        if (! $d) {
            return '—';
        }

        if (! $d instanceof CarbonInterface) {
            $d = $d instanceof \DateTimeInterface ? Carbon::instance($d) : Carbon::parse($d);
        }

        return $d->day.' '.self::MONTHS[$d->month - 1].($withYear ? ' '.$d->year : '');
    }

    public static function dateRange(?CarbonInterface $start, ?CarbonInterface $end, bool $withYear = true): string
    {
        if (! $start || ! $end) {
            return $start ? self::date($start, $withYear) : ($end ? self::date($end, $withYear) : '—');
        }

        $year = $withYear ? ' '.$end->year : '';

        if ($start->year !== $end->year) {
            return self::date($start).' – '.self::date($end);
        }

        if ($start->month === $end->month) {
            return $start->day.' – '.$end->day.' '.self::MONTHS[$end->month - 1].$year;
        }

        return self::date($start, false).' – '.self::date($end, false).$year;
    }
}
