<?php

namespace App\Policies\Concerns;

use App\Enums\Ability;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait ChecksProjectAccess
{
    /**
     * کاربر به پروژه دسترسی دارد اگر «دیدن همه‌ی پروژه‌ها» را داشته باشد یا عضو آن باشد.
     * عضویت مستقیم از جدول project_user چک می‌شود (وابسته به متد خاصی روی مدل User نیست).
     */
    protected function canAccessProject(User $user, int $projectId): bool
    {
        return $user->can(Ability::ProjectsViewAll->value)
            || DB::table('project_user')
                ->where('project_id', $projectId)
                ->where('user_id', $user->id)
                ->exists();
    }
}
