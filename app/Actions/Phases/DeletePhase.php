<?php

namespace App\Actions\Phases;

use App\Models\Phase;
use Illuminate\Support\Facades\DB;

class DeletePhase
{
    /** مرحله حذف می‌شود؛ تسک‌هایش حذف نمی‌شوند و بدون مرحله می‌مانند (phase_id با FK خالی می‌شود) */
    public function handle(Phase $phase): void
    {
        DB::transaction(function () use ($phase) {
            $project = $phase->project;

            $phase->delete();

            (new ReorderPhases)->normalize($project);
        });
    }
}
