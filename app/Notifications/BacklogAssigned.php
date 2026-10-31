<?php

namespace App\Notifications;

use App\Models\Backlog;
use App\Models\User;
use Illuminate\Notifications\Notification;

/** FR-41: user di-assign ke backlog sebagai Programmer atau QA. */
class BacklogAssigned extends Notification
{
    public function __construct(
        public readonly Backlog $backlog,
        public readonly string $as, // programmer | qa
        public readonly ?User $actor = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = "{$this->backlog->code} {$this->backlog->title}";
        $role = $this->as === 'qa' ? 'QA' : 'Programmer';

        return [
            'kind' => 'assign',
            'title' => 'Backlog baru untuk Anda',
            'text' => "Anda di-assign ke {$label} sebagai {$role}",
            'bold' => [$label],
            'project' => $this->backlog->project->name,
            'url' => route('backlogs.show', $this->backlog, absolute: false),
            'actor' => $this->actor?->name,
        ];
    }
}
