<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\DeadlineApproachingNotification;
use App\Support\Notifier;
use Illuminate\Console\Command;

class SendDeadlineReminders extends Command
{
    protected $signature = 'orbit:send-deadline-reminders';

    protected $description = 'یادآوری ددلاین نزدیک به مسئول تسک‌ها (هر تسک و هر ددلاین فقط یک‌بار)';

    public function handle(): int
    {
        if (! config('orbit.reminders.enabled')) {
            $this->info('یادآوری غیرفعال است.');

            return self::SUCCESS;
        }

        $sent = 0;

        Task::query()
            ->dueWithin((int) config('orbit.reminders.hours_before', 24))
            ->whereNotNull('assignee_id')
            ->whereHas('project', fn ($p) => $p->notArchived())
            ->where(fn ($q) => $q
                ->whereNull('deadline_reminded_for')
                ->orWhereColumn('deadline_reminded_for', '!=', 'due_date'))
            ->with('assignee')
            ->chunkById(200, function ($tasks) use (&$sent) {
                foreach ($tasks as $task) {
                    if ($task->assignee?->is_active) {
                        Notifier::send($task->assignee, new DeadlineApproachingNotification($task));
                        $sent++;
                    }

                    // حتی اگر مسئول غیرفعال باشد علامت می‌خورد تا هر روز دوباره بررسی نشود
                    Task::query()->whereKey($task->id)->toBase()
                        ->update(['deadline_reminded_for' => $task->due_date->toDateString()]);
                }
            });

        $this->info("{$sent} یادآوری ارسال شد.");

        return self::SUCCESS;
    }
}
