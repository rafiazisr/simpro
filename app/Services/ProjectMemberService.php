<?php

namespace App\Services;

use App\Models\Backlog;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectAssigned;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Aturan keanggotaan project yang dipakai bersama oleh tab Anggota project
 * dan form Manajemen User (bagian "Assign ke Project").
 */
class ProjectMemberService
{
    public function pmCount(Project $project): int
    {
        return $project->members()->wherePivot('project_role', 'pm')->count();
    }

    public function activeBacklogs(Project $project, User $user): int
    {
        return Backlog::where('project_id', $project->id)->assignedTo($user)->notDone()->count();
    }

    /** Project yang boleh diatur anggotanya oleh $actor. */
    public function manageableProjects(User $actor): Collection
    {
        return Project::query()->orderBy('name')->get(['id', 'code', 'name', 'status'])
            ->filter(fn (Project $p) => $actor->can('manageMembers', $p))
            ->values();
    }

    /**
     * Susun rencana penyamaan keanggotaan $user dengan $rows ([project_id => role])
     * untuk project yang boleh diatur $actor. Keanggotaan di project lain tidak disentuh.
     * Semua aturan dicek di sini; bila ada yang melanggar, dilempar ValidationException
     * sehingga belum ada data yang berubah.
     *
     * @param  array<int, string>  $rows
     * @return array{add: list<array{Project, string}>, change: list<array{Project, string}>, remove: list<Project>}
     */
    public function plan(User $user, array $rows, User $actor): array
    {
        $manageable = $this->manageableProjects($actor)->keyBy('id');
        $current = $user->exists
            ? $user->projects()->get()->keyBy('id')->map(fn (Project $p) => $p->pivot->project_role)
            : collect();

        $errors = [];
        $plan = ['add' => [], 'change' => [], 'remove' => []];

        foreach ($rows as $projectId => $role) {
            $project = $manageable->get($projectId);
            if (! $project) {
                $errors[] = 'Anda tidak berhak mengatur anggota salah satu project yang dipilih.';
                continue;
            }

            $old = $current->get($projectId);
            if ($old === null) {
                $plan['add'][] = [$project, $role];
            } elseif ($old !== $role) {
                if ($old === 'pm' && $this->pmCount($project) <= 1) {
                    $errors[] = "{$project->name} harus memiliki minimal 1 Project Manager.";
                } elseif ($this->activeBacklogs($project, $user) > 0) {
                    $errors[] = "{$user->name} masih punya backlog aktif di {$project->name}. Assign ulang backlog-nya sebelum mengganti role.";
                } else {
                    $plan['change'][] = [$project, $role];
                }
            }
        }

        foreach ($current as $projectId => $old) {
            if (array_key_exists($projectId, $rows) || ! ($project = $manageable->get($projectId))) {
                continue;
            }
            if ($old === 'pm' && $this->pmCount($project) <= 1) {
                $errors[] = "{$project->name} harus memiliki minimal 1 Project Manager.";
            } elseif (($n = $this->activeBacklogs($project, $user)) > 0) {
                $errors[] = "{$user->name} masih punya {$n} backlog aktif di {$project->name}. Assign ulang backlog-nya sebelum dikeluarkan.";
            } else {
                $plan['remove'][] = $project;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages(['projects' => array_values(array_unique($errors))]);
        }

        return $plan;
    }

    /**
     * Jalankan rencana dari plan() (dipanggil setelah user tersimpan).
     *
     * @return array{added: int, changed: int, removed: int}
     */
    public function apply(array $plan, User $user, User $actor): array
    {
        foreach ($plan['add'] as [$project, $role]) {
            $project->members()->attach($user->id, ['project_role' => $role]);
        }
        foreach ($plan['change'] as [$project, $role]) {
            $project->members()->updateExistingPivot($user->id, ['project_role' => $role]);
        }
        foreach ($plan['remove'] as $project) {
            $project->members()->detach($user->id);
        }

        if (! $user->is($actor)) {
            foreach ($plan['add'] as [$project, $role]) {
                $user->notify(new ProjectAssigned($project, $role, $actor));
            }
        }

        return ['added' => count($plan['add']), 'changed' => count($plan['change']), 'removed' => count($plan['remove'])];
    }
}
