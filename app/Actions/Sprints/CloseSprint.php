<?php

namespace App\Actions\Sprints;

use App\Enums\SprintStatus;
use App\Models\Sprint;
use App\Models\Task;
use DomainException;
use Illuminate\Support\Facades\DB;

class CloseSprint
{
    /**
     * @param  string  $carry  backlog = تسک‌های ناتمام از اسپرینت خارج می‌شوند | next = به اسپرینت برنامه‌ریزی‌شده‌ی بعدی می‌روند
     *
     * @throws DomainException
     */
    public function handle(Sprint $sprint, string $carry = 'backlog'): void
    {
        if ($sprint->status !== SprintStatus::Active) {
            throw new DomainException('فقط اسپرینت فعال بسته می‌شود.');
        }

        DB::transaction(function () use ($sprint, $carry) {
            $next = $carry === 'next'
                ? Sprint::query()
                    ->where('project_id', $sprint->project_id)
                    ->where('status', SprintStatus::Planned->value)
                    ->whereKeyNot($sprint->id)
                    ->orderBy('starts_at')
                    ->first()
                : null;

            Task::query()
                ->where('sprint_id', $sprint->id)
                ->where('status', '!=', 'done')
                ->update(['sprint_id' => $next?->id]);

            $sprint->update(['status' => SprintStatus::Closed]);
        });
    }
}
