<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    /**
     * @param  array{name:string,description:?string,status:string,color:string,client_id:?int,start_date:?string,end_date:?string}  $data
     * @param  array<int,int>  $memberIds  سازنده همیشه عضو می‌شود (تا پروژه‌ی خودش را ببیند)
     */
    public function handle(User $actor, array $data, array $memberIds, string $template = 'blank'): Project
    {
        return DB::transaction(function () use ($actor, $data, $memberIds, $template) {
            $project = Project::query()->create($data + ['owner_id' => $actor->id]);

            $project->members()->sync(
                collect($memberIds)->map(fn ($id) => (int) $id)->push($actor->id)->unique()->values()->all()
            );

            (new ApplyPhaseTemplate)->handle($project, $template);

            return $project;
        });
    }
}
