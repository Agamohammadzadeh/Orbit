<?php

namespace App\Actions\Projects;

use App\Models\Project;

class SetProjectArchived
{
    /** آرشیو یا بازگردانی؛ تسک‌ها و اعضا دست نمی‌خورند. دسترسی را قبلاً با Gate چک کن. */
    public function handle(Project $project, bool $archived): Project
    {
        $project->update(['archived_at' => $archived ? now() : null]);

        return $project;
    }
}
