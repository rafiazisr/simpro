<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacklogActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['backlog_id', 'user_id', 'field', 'old_value', 'new_value', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function backlog(): BelongsTo
    {
        return $this->belongsTo(Backlog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Kalimat aktivitas untuk riwayat, mis. "mengubah status". */
    public function verb(): string
    {
        return match ($this->field) {
            'created' => 'membuat backlog ini',
            'status' => 'mengubah status',
            'priority' => 'mengubah prioritas',
            'programmer' => 'mengubah programmer',
            'qa' => 'mengubah QA',
            'sprint' => 'memindahkan sprint',
            'start_date' => 'mengubah tanggal mulai',
            'end_date' => 'mengubah tanggal selesai',
            'title' => 'mengubah judul',
            default => 'mengubah '.$this->field,
        };
    }

    public function hasChange(): bool
    {
        return $this->field !== 'created';
    }
}
