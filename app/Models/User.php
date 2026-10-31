<?php

namespace App\Models;

use App\Enums\UserLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'user_level',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'user_level' => UserLevel::class,
        ];
    }

    protected static function booted(): void
    {
        // Role spatie level selalu mengikuti kolom user_level.
        static::saved(function (User $user) {
            if ($user->wasChanged('user_level') || $user->wasRecentlyCreated) {
                $levelRoles = array_map(fn (UserLevel $l) => $l->roleName(), UserLevel::cases());
                $keep = $user->roles->pluck('name')->diff($levelRoles)->all();
                $user->syncRoles([...$keep, $user->user_level->roleName()]);
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->user_level === UserLevel::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return in_array($this->user_level, [UserLevel::SuperAdmin, UserLevel::Admin], true);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withPivot('project_role')->withTimestamps();
    }

    /**
     * Cek permission di dalam sebuah project: lolos jika permission dimiliki secara
     * global (lewat level user, mis. Admin) ATAU lewat role project user di project itu.
     */
    public function canInProject(string $permission, Project $project): bool
    {
        if ($this->can($permission)) {
            return true;
        }

        $roleName = $project->roleOf($this);

        if (! $roleName) {
            return false;
        }

        try {
            return Role::findByName($roleName, 'web')->hasPermissionTo($permission, 'web');
        } catch (RoleDoesNotExist|PermissionDoesNotExist) {
            return false;
        }
    }

    /** Inisial untuk avatar, mis. "Budi Santoso" -> "BS". */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $w) => Str::upper(Str::substr($w, 0, 1)))
            ->implode('');
    }

    /** Warna avatar c1..c8 (class desain) yang stabil per user. */
    public function avatarColor(): string
    {
        return 'c'.(($this->id % 8) + 1);
    }

    /** Putuskan semua sesi login user (session driver database). */
    public function terminateSessions(): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $this->getKey())->delete();
        }

        $this->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
        }));
    }
}
