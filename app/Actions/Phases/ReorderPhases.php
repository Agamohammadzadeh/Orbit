<?php

namespace App\Actions\Phases;

use App\Models\Phase;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class ReorderPhases
{
    /** مرحله را به جایگاه جدید (از صفر) می‌برد و همه‌ی ترتیب‌ها را ۱ تا n بازشماره می‌کند */
    public function handle(Project $project, int $phaseId, int $position): void
    {
        DB::transaction(function () use ($project, $phaseId, $position) {
            $ids = $this->ids($project);

            if (! in_array($phaseId, $ids, true)) {
                return;
            }

            $ids = array_values(array_filter($ids, fn ($id) => $id !== $phaseId));
            array_splice($ids, max(0, min($position, count($ids))), 0, [$phaseId]);

            $this->apply($ids);
        });
    }

    /** ترتیب‌ها را بدون تغییر جایگاه‌ها، پشت‌سرهم (۱ تا n) می‌کند (بعد از حذف) */
    public function normalize(Project $project): void
    {
        $this->apply($this->ids($project));
    }

    /** @return array<int,int> */
    private function ids(Project $project): array
    {
        return Phase::query()
            ->where('project_id', $project->id)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @param array<int,int> $ids */
    private function apply(array $ids): void
    {
        foreach ($ids as $i => $id) {
            Phase::query()->whereKey($id)->toBase()->update(['position' => $i + 1]);
        }
    }
}
