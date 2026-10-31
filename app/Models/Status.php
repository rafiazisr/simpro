<?php

namespace App\Models;

use App\Enums\StatusCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    protected $fillable = ['project_id', 'name', 'category', 'style', 'sort_order'];

    protected function casts(): array
    {
        return ['category' => StatusCategory::class];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function backlogs(): HasMany
    {
        return $this->hasMany(Backlog::class);
    }

    public function isCustom(): bool
    {
        return $this->project_id !== null;
    }

    public function badgeClass(): string
    {
        return 'badge st-'.($this->style ?? 'custom');
    }

    public function color(): string
    {
        return [
            'todo' => '#94A3B8', 'progress' => '#3B82F6', 'readyqa' => '#F59E0B',
            'testing' => '#8B5CF6', 'done' => '#22C55E', 'reopen' => '#EF4444',
        ][$this->style ?? $this->category->style()] ?? '#94A3B8';
    }
}
