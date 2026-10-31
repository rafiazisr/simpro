<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DocFeature extends Model
{
    protected $fillable = ['doc_module_id', 'title', 'content', 'sort_order', 'updated_by'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(DocModule::class, 'doc_module_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /** Backlog terkait; status selalu dibaca langsung dari backlog (FR-32). */
    public function backlogs(): BelongsToMany
    {
        return $this->belongsToMany(Backlog::class, 'backlog_doc_feature');
    }

    public function projectId(): int
    {
        return $this->module->project_id;
    }
}
