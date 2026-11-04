<?php

namespace App\Actions\Sprints;

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;

class SaveSprint
{
    /**
     * @param  Project  $project  :?string,starts_at:string,ends_at:string} $data
     */
    public function handle(Project $project, ?Sprint $sprint, array $data): Sprint
    {
        if ($sprint) {
            $sprint->update($data);

            return $sprint;
        }

        return Sprint::query()->create($data + [
            'project_id' => $project->id,
            'status' => SprintStatus::Planned,
        ]);
    }
}
