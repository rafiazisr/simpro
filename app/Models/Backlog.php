<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\StatusCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Backlog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'sprint_id', 'number', 'code', 'title', 'detail', 'status_id', 'priority',
        'programmer_id', 'qa_id', 'start_date', 'end_date', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_at' => 'datetime',
            'priority' => Priority::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function programmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'programmer_id')->withTrashed();
    }

    public function qa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** Fitur dokumentasi yang terhubung ke backlog ini. */
    public function docFeatures(): BelongsToMany
    {
        return $this->belongsToMany(DocFeature::class, 'backlog_doc_feature');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BacklogActivity::class)->latest('created_at')->latest('id');
    }

    public function category(): StatusCategory
    {
        return $this->status->category;
    }

    public function isDone(): bool
    {
        return $this->category() === StatusCategory::Done;
    }

    /** Belum Done dan tanggal selesai sudah lewat. */
    public function isLate(): bool
    {
        return $this->end_date !== null && ! $this->isDone() && $this->end_date->lt(today());
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->programmer_id === $user->id || $this->qa_id === $user->id;
    }

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('programmer_id', $user->id)->orWhere('qa_id', $user->id));
    }

    public function scopeNotDone(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $q) => $q->where('category', '!=', StatusCategory::Done));
    }
}
