<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnHold => 'On Hold',
            self::Done => 'Done',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge ps-active',
            self::OnHold => 'badge ps-hold',
            self::Done => 'badge ps-done',
        };
    }
}
