<?php

namespace App\Services;

use App\Enums\StatusCategory;
use App\Models\Project;
use App\Models\Status;
use Illuminate\Support\Str;

class StatusService
{
    /**
     * Cari status berdasarkan nama (tidak peka huruf besar/kecil) di antara status
     * bawaan dan status kustom project. Jika belum ada, buat status kustom baru
     * berkategori To Do (FR-13).
     */
    public function resolve(Project $project, string $name): Status
    {
        $name = Str::of($name)->squish()->limit(50, '')->toString();

        $existing = $project->availableStatuses()
            ->first(fn (Status $s) => Str::lower($s->name) === Str::lower($name));

        if ($existing) {
            return $existing;
        }

        return Status::create([
            'project_id' => $project->id,
            'name' => $name,
            'category' => StatusCategory::Todo,
            'style' => null,
            'sort_order' => 200,
        ]);
    }

    /** Status bawaan berdasarkan nama, mis. "Ready for QA". */
    public function default(string $name): Status
    {
        return Status::query()->whereNull('project_id')->where('name', $name)->firstOrFail();
    }
}
