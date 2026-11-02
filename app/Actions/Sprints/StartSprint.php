<?php

namespace App\Actions\Sprints;

use App\Enums\SprintStatus;
use App\Models\Sprint;
use DomainException;

class StartSprint
{
    /** @throws DomainException */
    public function handle(Sprint $sprint): void
    {
        if ($sprint->status !== SprintStatus::Planned) {
            throw new DomainException('فقط اسپرینت برنامه‌ریزی‌شده شروع می‌شود.');
        }

        $hasActive = Sprint::query()
            ->where('project_id', $sprint->project_id)
            ->active()
            ->exists();

        if ($hasActive) {
            throw new DomainException('هم‌اکنون یک اسپرینت فعال داری. اول آن را ببند.');
        }

        $sprint->update(['status' => SprintStatus::Active]);
    }
}
