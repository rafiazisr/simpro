<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class Format
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const MONTHS_LONG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

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

    /** Contoh: "Kamis, 5 November 2026". */
    public static function longDate(CarbonInterface $d): string
    {
        return self::DAYS[$d->dayOfWeek].', '.$d->day.' '.self::MONTHS_LONG[$d->month - 1].' '.$d->year;
    }

    /** Sapaan sesuai jam: pagi (04–10), siang (11–14), sore (15–17), malam. */
    public static function greeting(CarbonInterface $d): string
    {
        return match (true) {
            $d->hour >= 4 && $d->hour < 11 => 'Selamat pagi',
            $d->hour >= 11 && $d->hour < 15 => 'Selamat siang',
            $d->hour >= 15 && $d->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
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
