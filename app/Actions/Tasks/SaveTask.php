<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Support\Facades\DB;

class SaveTask
{
    /**
     * ساخت یا ویرایش تسک. اعتبارسنجی و دسترسی با کامپوننت است.
     *
     * @param  User  $actor  :?string,status:string,priority:string,type:string,assignee_id:?int,phase_id:?int,sprint_id:?int,due_date:?string,estimate_minutes:?int}  $data
     * @param  array<int,int>  $labelIds
     *
     * @throws \Throwable
     */
    public function handle(User $actor, Project $project, ?Task $task, array $data, array $labelIds): Task
    {
        $oldAssignee = $task?->assignee_id;

        $task = DB::transaction(function () use ($actor, $project, $task, $data, $labelIds) {
            $status = TaskStatus::from($data['status']);
            unset($data['status']);

            if ($task) {
                $task->update($data);
                // تغییر وضعیت ته ستون جدید می‌نشیند و completed_at هماهنگ می‌شود
                (new ChangeTaskStatus)->handle($actor, $task, $status);
            } else {
                $task = Task::query()->create($data + [
                    'project_id' => $project->id,
                    'creator_id' => $actor->id,
                    'status' => $status,
                ]);
            }

            $task->labels()->sync($labelIds);

            return $task;
        });

        // اعلان ارجاع به مسئول جدید (به خودت اعلان نمی‌رسد)
        $new = $task->assignee_id;
        if ($new && $new !== $oldAssignee && $new !== $actor->id) {
            User::query()->active()->find($new)?->notify(new TaskAssignedNotification($task, $actor));
        }

        return $task;
    }
}
