<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\StatusCategory;
use App\Models\Backlog;
use App\Models\BacklogActivity;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\User;
use App\Notifications\BacklogAssigned;
use App\Notifications\BacklogReadyForQa;
use App\Support\Format;
use Illuminate\Support\Facades\DB;

class BacklogService
{
    private const TRACKED = ['status_id', 'priority', 'programmer_id', 'qa_id', 'sprint_id', 'start_date', 'end_date', 'title'];

    public function create(Project $project, array $data, User $actor): Backlog
    {
        $backlog = DB::transaction(function () use ($project, $data, $actor) {
            Project::query()->whereKey($project->id)->lockForUpdate()->first();

            $number = (int) Backlog::withTrashed()->where('project_id', $project->id)->max('number') + 1;

            $backlog = Backlog::create($data + [
                'project_id' => $project->id,
                'number' => $number,
                'code' => $project->code.'-'.$number,
                'created_by' => $actor->id,
            ]);

            $this->syncCompletedAt($backlog);
            $this->log($backlog, $actor, 'created');

            return $backlog;
        });

        $this->notifyChanges($backlog, [], $actor);

        return $backlog;
    }

    public function update(Backlog $backlog, array $data, User $actor): Backlog
    {
        $before = $backlog->only(self::TRACKED);

        DB::transaction(function () use ($backlog, $data, $actor, $before) {
            $backlog->fill($data)->save();
            $this->syncCompletedAt($backlog);

            foreach (self::TRACKED as $field) {
                $old = $before[$field] ?? null;
                $new = $backlog->getAttribute($field);

                if ($this->normalize($old) !== $this->normalize($new)) {
                    $this->log($backlog, $actor, $field, $this->display($field, $old), $this->display($field, $new));
                }
            }
        });

        $this->notifyChanges($backlog, $before, $actor);

        return $backlog;
    }

    private function notifyChanges(Backlog $backlog, array $before, User $actor): void
    {
        $backlog->load(['status', 'project', 'programmer', 'qa']);

        foreach (['programmer_id' => 'programmer', 'qa_id' => 'qa'] as $field => $as) {
            $userId = (int) $backlog->getAttribute($field);

            if ($userId && $userId !== (int) ($before[$field] ?? 0) && $userId !== $actor->id) {
                User::find($userId)?->notify(new BacklogAssigned($backlog, $as, $actor));
            }
        }

        $wasReady = isset($before['status_id']) && Status::find($before['status_id'])?->category === StatusCategory::ReadyForQa;
        $isReady = $backlog->status->category === StatusCategory::ReadyForQa;

        if ($isReady && ! $wasReady && $backlog->qa && (int) $backlog->qa_id !== $actor->id) {
            $backlog->qa->notify(new BacklogReadyForQa($backlog, $actor));
        }
    }

    public function changeStatus(Backlog $backlog, Status $status, User $actor): Backlog
    {
        return $this->update($backlog, ['status_id' => $status->id], $actor);
    }

    private function syncCompletedAt(Backlog $backlog): void
    {
        $backlog->load('status');
        $done = $backlog->status->category === StatusCategory::Done;

        if ($done && ! $backlog->completed_at) {
            $backlog->forceFill(['completed_at' => now()])->saveQuietly();
        } elseif (! $done && $backlog->completed_at) {
            $backlog->forceFill(['completed_at' => null])->saveQuietly();
        }
    }

    private function log(Backlog $backlog, User $actor, string $field, ?string $old = null, ?string $new = null): void
    {
        BacklogActivity::create([
            'backlog_id' => $backlog->id,
            'user_id' => $actor->id,
            'field' => match ($field) {
                'status_id' => 'status',
                'programmer_id' => 'programmer',
                'qa_id' => 'qa',
                'sprint_id' => 'sprint',
                default => $field,
            },
            'old_value' => $old,
            'new_value' => $new,
            'created_at' => now(),
        ]);
    }

    private function normalize(mixed $v): ?string
    {
        return match (true) {
            $v === null || $v === '' => null,
            $v instanceof \DateTimeInterface => $v->format('Y-m-d'),
            $v instanceof \BackedEnum => (string) $v->value,
            default => (string) $v,
        };
    }

    private function display(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return match ($field) {
                'sprint_id' => 'Product Backlog',
                'programmer_id', 'qa_id' => 'Belum di-assign',
                default => '—',
            };
        }

        return match ($field) {
            'status_id' => Status::find($value)?->name,
            'priority' => ($value instanceof Priority ? $value : Priority::from($value))->label(),
            'programmer_id', 'qa_id' => User::withTrashed()->find($value)?->name,
            'sprint_id' => Sprint::find($value)?->name,
            'start_date', 'end_date' => Format::date($value),
            default => \Illuminate\Support\Str::limit((string) $value, 120),
        };
    }
}
