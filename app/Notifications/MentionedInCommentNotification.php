<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;

class MentionedInCommentNotification extends OrbitNotification
{
    public function __construct(public Task $task, public User $actor) {}

    public function type(): NotificationType
    {
        return NotificationType::Mention;
    }

    public function message(): string
    {
        return "{$this->actor->name} تو را در تسک «".Str::limit($this->task->title, 60).'» منشن کرد.';
    }

    public function path(): string
    {
        return route('tasks.show', $this->task, false);
    }

    protected function extra(): array
    {
        return ['task_id' => $this->task->id, 'actor_id' => $this->actor->id];
    }
}
