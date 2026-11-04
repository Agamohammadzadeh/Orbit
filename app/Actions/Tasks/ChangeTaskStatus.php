<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

class ChangeTaskStatus
{
    /**
     * $actor فعلاً استفاده نمی‌شود؛ برای رویداد TaskStatusChanged و اعلان‌ها
     * (مرحله‌ی بعد) نگه داشته شده است. دسترسی را باید قبل از صدا زدن
     * با Gate::authorize('update', $task) چک کنی.
     */
    public function handle(User $actor, Task $task, TaskStatus $status): Task
    {
        if ($task->status === $status) {
            return $task;
        }

        $max = Task::query()
            ->where('project_id', $task->project_id)
            ->where('status', $status->value)
            ->max('position');

        $task->status = $status;
        $task->position = ($max ?? 0) + (int) config('orbit.kanban.position_gap', 1000);
        $task->save();

        return $task;
    }
}
