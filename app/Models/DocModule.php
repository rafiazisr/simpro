<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocModule extends Model
{
    protected $fillable = ['project_id', 'name', 'sort_order'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(DocFeature::class)->orderBy('sort_order')->orderBy('id');
    }
}
