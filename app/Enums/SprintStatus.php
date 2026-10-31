<?php

namespace App\Enums;

enum SprintStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Active => 'Active',
            self::Done => 'Done',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Planned => 'badge ps-planned',
            self::Active => 'badge ps-active',
            self::Done => 'badge ps-done',
        };
    }
}
