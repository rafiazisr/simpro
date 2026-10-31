<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class ProjectRoles
{
    /** @return Collection<string, string> name => label, role bawaan dulu lalu kustom. */
    public static function options(): Collection
    {
        $order = ['pm' => 1, 'programmer' => 2, 'qa' => 3];

        return Role::query()->where('scope', 'project')->get()
            ->sortBy(fn (Role $r) => [$order[$r->name] ?? 50, $r->id])
            ->mapWithKeys(fn (Role $r) => [$r->name => $r->label ?? $r->name]);
    }

    public static function label(?string $name): string
    {
        return self::options()[$name] ?? (string) $name;
    }

    /** Class badge desain untuk role project. */
    public static function badgeClass(?string $name): string
    {
        return match ($name) {
            'pm' => 'badge role-pm',
            'programmer' => 'badge role-prog',
            'qa' => 'badge role-qa',
            default => 'badge badge-neutral',
        };
    }
}
