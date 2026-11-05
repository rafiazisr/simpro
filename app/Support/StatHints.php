<?php

namespace App\Support;

use App\Models\Backlog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keterangan untuk bilah statistik yang berisi data nyata (kode backlog, lama
 * keterlambatan), bukan teks pengisi yang mengulang label.
 */
class StatHints
{
    /**
     * Kode backlog teratas sebagai tautan, mis. "PHR-3, PHR-5 +1".
     *
     * @return array{refs: \Illuminate\Support\Collection<int, Backlog>, more: int}
     */
    public static function codes(Builder $query, int $limit = 2): array
    {
        $total = (clone $query)->count();
        $refs = (clone $query)->orderBy('updated_at')->limit($limit)->get(['id', 'code']);

        return ['refs' => $refs, 'more' => max(0, $total - $refs->count())];
    }

    /**
     * Backlog yang paling lama terlambat, mis. "PHR-6 · telat 3 hari".
     *
     * @return array{refs: \Illuminate\Support\Collection<int, Backlog>, hint: ?string}
     */
    public static function oldestLate(Builder $lateQuery): array
    {
        $oldest = (clone $lateQuery)->orderBy('end_date')->first(['id', 'code', 'end_date']);
        if (! $oldest) {
            return ['refs' => collect(), 'hint' => null];
        }

        $days = (int) $oldest->end_date->diffInDays(today());

        return ['refs' => collect([$oldest]), 'hint' => 'telat '.$days.' hari'];
    }
}
