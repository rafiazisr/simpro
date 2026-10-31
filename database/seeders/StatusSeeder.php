<?php

namespace Database\Seeders;

use App\Enums\StatusCategory;
use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    public const DEFAULTS = [
        ['To Do', StatusCategory::Todo, 'todo'],
        ['In Progress', StatusCategory::InProgress, 'progress'],
        ['Ready for QA', StatusCategory::ReadyForQa, 'readyqa'],
        ['Testing', StatusCategory::Testing, 'testing'],
        ['Done', StatusCategory::Done, 'done'],
        ['Reopen', StatusCategory::InProgress, 'reopen'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $i => [$name, $category, $style]) {
            Status::query()->updateOrCreate(
                ['project_id' => null, 'name' => $name],
                ['category' => $category, 'style' => $style, 'sort_order' => ($i + 1) * 10],
            );
        }
    }
}
