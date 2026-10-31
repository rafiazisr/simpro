<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\StatusCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    public const COLORS = ['#3B82F6', '#8B5CF6', '#F59E0B', '#22C55E', '#EC4899', '#14B8A6', '#F97316', '#6366F1'];

    protected $fillable = [
        'code', 'name', 'description', 'start_date', 'end_date', 'status', 'color', 'done_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'done_at' => 'datetime',
            'status' => ProjectStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('project_role')->withTimestamps();
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class)->orderByDesc('start_date');
    }

    public function backlogs(): HasMany
    {
        return $this->hasMany(Backlog::class);
    }

    public function docModules(): HasMany
    {
        return $this->hasMany(DocModule::class)->orderBy('sort_order')->orderBy('id');
    }

    public function customStatuses(): HasMany
    {
        return $this->hasMany(Status::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function roleOf(User $user): ?string
    {
        if ($this->relationLoaded('members')) {
            return $this->members->firstWhere('id', $user->id)?->pivot->project_role;
        }

        return $this->members()->where('users.id', $user->id)->value('project_role');
    }

    public function membersWithRole(string $role)
    {
        return $this->members->filter(fn (User $u) => $u->pivot->project_role === $role)->values();
    }

    public function availableStatuses()
    {
        return Status::query()
            ->where(fn (Builder $q) => $q->whereNull('project_id')->orWhere('project_id', $this->id))
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    public function progress(): int
    {
        $total = $this->backlogs_count ?? $this->backlogs()->count();
        $done = $this->done_backlogs_count ?? $this->backlogs()->whereHas('status', fn ($q) => $q->where('category', StatusCategory::Done))->count();

        return $total ? (int) round($done / $total * 100) : 0;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('members', fn (Builder $q) => $q->where('users.id', $user->id));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")));
    }
}
