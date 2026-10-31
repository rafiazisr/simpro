<?php

namespace App\Enums;

/**
 * Kategori tetap di balik status dinamis. Dipakai untuk pie chart,
 * pemicu notifikasi QA, dan perhitungan progres (FR-14).
 */
enum StatusCategory: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case ReadyForQa = 'ready_for_qa';
    case Testing = 'testing';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::ReadyForQa => 'Ready for QA',
            self::Testing => 'Testing',
            self::Done => 'Done',
        };
    }

    /** Kunci warna desain (st-*). */
    public function style(): string
    {
        return match ($this) {
            self::Todo => 'todo',
            self::InProgress => 'progress',
            self::ReadyForQa => 'readyqa',
            self::Testing => 'testing',
            self::Done => 'done',
        };
    }

    /** Masih menjadi pekerjaan programmer (termasuk Reopen yang berkategori In Progress). */
    public function isProgrammerStage(): bool
    {
        return in_array($this, [self::Todo, self::InProgress], true);
    }

    public function isQaStage(): bool
    {
        return in_array($this, [self::ReadyForQa, self::Testing], true);
    }
}
