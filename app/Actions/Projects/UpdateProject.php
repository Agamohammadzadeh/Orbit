<?php

namespace App\Actions\Projects;

use App\Enums\Ability;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateProject
{
    /**
     * @param  array<string,mixed>  $data  فقط کلیدهای fillable پروژه
     * @param  array<int,int>  $memberIds
     */
    public function handle(User $actor, Project $project, array $data, array $memberIds): Project
    {
        $ids = collect($memberIds)->map(fn ($id) => (int) $id);

        // کسی که «دیدن همه‌ی پروژه‌ها» را ندارد، نمی‌تواند خودش را از پروژه بیرون بیندازد
        if (! $actor->can(Ability::ProjectsViewAll->value)) {
            $ids->push($actor->id);
        }

        DB::transaction(function () use ($project, $data, $ids) {
            $project->update($data);
            $project->members()->sync($ids->unique()->values()->all());
        });

        return $project->refresh();
    }
}
