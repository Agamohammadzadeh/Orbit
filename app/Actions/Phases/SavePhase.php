<?php

namespace App\Actions\Phases;

use App\Models\Phase;
use App\Models\Project;

class SavePhase
{
    /** ساخت (ته فهرست) یا تغییر نام مرحله. دسترسی را قبلاً با Gate چک کن. */
    public function handle(Project $project, ?Phase $phase, string $name): Phase
    {
        $name = trim($name);

        if ($phase) {
            $phase->update(['name' => $name]);

            return $phase;
        }

        $position = (int) Phase::query()->where('project_id', $project->id)->max('position');

        return Phase::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'position' => $position + 1,
        ]);
    }
}
