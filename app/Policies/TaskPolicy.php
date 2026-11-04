<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAccess;

class TaskPolicy
{
    use ChecksProjectAccess;

    public function view(User $user, Task $task): bool
    {
        return $this->canAccessProject($user, $task->project_id);
    }

    /** استفاده: $user->can('create', [Task::class, $project]) */
    public function create(User $user, Project $project): bool
    {
        return $user->can(Ability::TasksWrite->value)
            && $this->canAccessProject($user, $project->id);
    }

    /** ویرایش، تغییر وضعیت، جابه‌جایی در کانبان، چک‌لیست، پیوست و ثبت زمان */
    public function update(User $user, Task $task): bool
    {
        return $user->can(Ability::TasksWrite->value)
            && $this->canAccessProject($user, $task->project_id);
    }

    public function delete(User $user, Task $task): bool
    {
        if (! $this->canAccessProject($user, $task->project_id)) {
            return false;
        }

        // حذف تسک دیگران با «حذف هر تسک»؛ تسک خودش با همان «ساخت و ویرایش»
        return $user->can(Ability::TasksDelete->value)
            || ($user->can(Ability::TasksWrite->value) && $task->creator_id === $user->id);
    }
}
