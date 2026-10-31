<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class ProjectRoles
{
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

    public static function badgeClass(?string $name): string
    {
        return $name === 'pm' ? 'tag tag-strong' : 'tag';
    }
}
