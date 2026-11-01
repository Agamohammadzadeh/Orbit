<?php

namespace App\Actions\Projects;

use App\Models\Phase;
use App\Models\Project;

class ApplyPhaseTemplate
{
    /** مراحل قالب (config('orbit.phase_templates')) را برای پروژه می‌سازد */
    public function handle(Project $project, string $template): void
    {
        $phases = config("orbit.phase_templates.{$template}.phases", []);

        foreach (array_values($phases) as $i => $name) {
            Phase::query()->create([
                'project_id' => $project->id,
                'name' => $name,
                'position' => $i + 1,
            ]);
        }
    }
}
