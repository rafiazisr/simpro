<?php

namespace App\Models;

use App\Enums\SprintStatus;
use App\Support\Format;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'name', 'goal', 'start_date', 'end_date', 'status', 'done_at'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'done_at' => 'datetime',
            'status' => SprintStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function backlogs(): HasMany
    {
        return $this->hasMany(Backlog::class);
    }

    public function rangeLabel(): string
    {
        return Format::dateRange($this->start_date, $this->end_date);
    }

    public function weeks(): int
    {
        return (int) ceil(($this->start_date->diffInDays($this->end_date) + 1) / 7);
    }
}
