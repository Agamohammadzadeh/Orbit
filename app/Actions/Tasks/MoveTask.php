<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MoveTask
{
    /**
     * تسک را بین دو همسایه‌اش در ستون مقصد می‌گذارد.
     * ترتیب با ستون position و فاصله‌ی ثابت ذخیره می‌شود؛ اگر جا نماند، ستون یک‌بار بازشماره می‌شود.
     * دسترسی را قبلاً با Gate::authorize('update', $task) چک کن.
     */
    public function handle(User $actor, Task $task, TaskStatus $status, ?int $prevId, ?int $nextId): Task
    {
        $gap = (int) config('orbit.kanban.position_gap', 1000);

        return DB::transaction(function () use ($task, $status, $prevId, $nextId, $gap) {
            $new = $this->between($this->position($task, $status, $prevId), $this->position($task, $status, $nextId), $gap);

            if ($new === null) {
                $this->renumber($task, $status, $gap);
                $new = $this->between($this->position($task, $status, $prevId), $this->position($task, $status, $nextId), $gap) ?? $gap;
            }

            $task->status = $status;   // completed_at را خود مدل (Task::booted) هماهنگ می‌کند
            $task->position = $new;
            $task->save();

            return $task;
        });
    }

    private function position(Task $task, TaskStatus $status, ?int $id): ?int
    {
        if (! $id) {
            return null;
        }

        return Task::query()
            ->where('project_id', $task->project_id)
            ->where('status', $status->value)
            ->whereKeyNot($task->id)
            ->whereKey($id)
            ->value('position');
    }

    private function between(?int $prev, ?int $next, int $gap): ?int
    {
        return match (true) {
            $prev === null && $next === null => $gap,
            $prev === null => $next > 1 ? intdiv($next, 2) : null,
            $next === null => $prev + $gap,
            default => ($next - $prev) > 1 ? $prev + intdiv($next - $prev, 2) : null,
        };
    }

    private function renumber(Task $task, TaskStatus $status, int $gap): void
    {
        $ids = Task::query()
            ->where('project_id', $task->project_id)
            ->where('status', $status->value)
            ->whereKeyNot($task->id)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $i => $id) {
            Task::query()->whereKey($id)->toBase()->update(['position' => ($i + 1) * $gap]);
        }
    }
}
