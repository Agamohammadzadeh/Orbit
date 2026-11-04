<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;

class TaskAssignedNotification extends OrbitNotification
{
    public function __construct(public Task $task, public User $actor) {}

    public function type(): NotificationType
    {
        return NotificationType::TaskAssigned;
    }

    public function message(): string
    {
        return "{$this->actor->name} تسک «".Str::limit($this->task->title, 60).'» را به تو ارجاع داد.';
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
