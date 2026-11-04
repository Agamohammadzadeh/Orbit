<?php

namespace App\Queries;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/** فیلترهای مشترک برد و لیست تسک‌های یک پروژه */
class ProjectTaskFilter
{
    public static function apply(Builder $query, Project $project, int $userId, string $search, string $assignee, string $sprint, string $phase = ''): Builder
    {
        $term = str_replace(['%', '_', '\\'], '', trim($search));

        return $query
            ->where('project_id', $project->id)
            ->when($term !== '', fn ($q) => $q->whereLike('title', "%{$term}%"))
            ->when($assignee === 'me', fn ($q) => $q->where('assignee_id', $userId))
            ->when($assignee === 'none', fn ($q) => $q->whereNull('assignee_id'))
            ->when(ctype_digit($assignee), fn ($q) => $q->where('assignee_id', (int) $assignee))
            ->when($sprint === 'none', fn ($q) => $q->whereNull('sprint_id'))
            ->when($sprint === 'active', fn ($q) => $q->whereIn(
                'sprint_id',
                $project->sprints()->active()->reorder()->select('sprints.id')
            ))
            ->when(ctype_digit($sprint), fn ($q) => $q->where('sprint_id', (int) $sprint))
            ->when($phase === 'none', fn ($q) => $q->whereNull('phase_id'))
            ->when(ctype_digit($phase), fn ($q) => $q->where('phase_id', (int) $phase));
    }
}
