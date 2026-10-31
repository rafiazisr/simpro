<?php

namespace App\Notifications;

use App\Models\Backlog;
use App\Models\User;
use Illuminate\Notifications\Notification;

class BacklogReadyForQa extends Notification
{
    public function __construct(
        public readonly Backlog $backlog,
        public readonly ?User $actor = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $by = $this->backlog->programmer?->name ?? $this->actor?->name ?? 'programmer';

        return [
            'kind' => 'qa',
            'title' => 'Siap dites',
            'text' => "{$this->backlog->code} siap dites oleh QA (diselesaikan oleh {$by})",
            'bold' => [$this->backlog->code, $by],
            'project' => $this->backlog->project->name,
            'url' => route('backlogs.show', $this->backlog, absolute: false),
            'actor' => $this->actor?->name,
        ];
    }
}
