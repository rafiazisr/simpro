<?php

namespace App\Enums;

enum UserLevel: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::User => 'User',
        };
    }

    /** Class badge sesuai desain (lvl-super, badge-primary, badge-neutral). */
    public function badgeClass(): string
    {
        return match ($this) {
            self::SuperAdmin => 'badge lvl-super',
            self::Admin => 'badge badge-primary',
            self::User => 'badge badge-neutral',
        };
    }

    /** Nama role spatie yang dipasangkan dengan level ini. */
    public function roleName(): string
    {
        return $this->value;
    }

    /** Level yang boleh dipilih lewat form (Super Admin hanya lewat seeder). */
    public static function assignable(): array
    {
        return [self::User, self::Admin];
    }
}
