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

    public function badgeClass(): string
    {
        return match ($this) {
            self::SuperAdmin => 'tag tag-dark',
            self::Admin => 'tag tag-strong',
            self::User => 'tag',
        };
    }

    public function roleName(): string
    {
        return $this->value;
    }

    public static function assignable(): array
    {
        return [self::User, self::Admin];
    }
}
