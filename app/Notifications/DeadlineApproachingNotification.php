<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Task;
use App\Support\Dates;
use Illuminate\Support\Str;

class DeadlineApproachingNotification extends OrbitNotification
{
    public function __construct(public Task $task) {}

    public function type(): NotificationType
    {
        return NotificationType::Deadline;
    }

    public function message(): string
    {
        $days = Dates::daysFromToday($this->task->due_date);

        $when = match (true) {
            $days === null || $days <= 0 => 'امروز',
            $days === 1 => 'فردا',
            default => "{$days} روز دیگر",
        };

        return "مهلت تسک «".Str::limit($this->task->title, 60)."» {$when} است.";
    }

    public function path(): string
    {
        return route('tasks.show', $this->task, false);
    }

    protected function extra(): array
    {
        return ['task_id' => $this->task->id];
    }
}
