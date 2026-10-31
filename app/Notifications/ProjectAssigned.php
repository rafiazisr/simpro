<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use App\Support\ProjectRoles;
use Illuminate\Notifications\Notification;

class ProjectAssigned extends Notification
{
    public function __construct(
        public readonly Project $project,
        public readonly string $role,
        public readonly ?User $actor = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $role = ProjectRoles::label($this->role);

        return [
            'kind' => 'project',
            'title' => 'Ditambahkan ke project',
            'text' => "Anda ditambahkan ke project {$this->project->name} sebagai {$role}",
            'bold' => [$this->project->name, $role],
            'project' => $this->project->name,
            'url' => route('projects.show', $this->project, absolute: false),
            'actor' => $this->actor?->name,
        ];
    }
}
