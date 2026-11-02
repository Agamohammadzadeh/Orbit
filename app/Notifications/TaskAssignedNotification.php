<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskAssignedNotification extends Notification
{
    public function __construct(public Task $task, public User $actor) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'message' => "{$this->actor->name} تسک «".Str::limit($this->task->title, 60).'» را به تو ارجاع داد.',
            'url' => route('tasks.show', $this->task, false),
            'task_id' => $this->task->id,
            'actor_id' => $this->actor->id,
        ];
    }
}
