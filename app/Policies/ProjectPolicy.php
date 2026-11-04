<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAccess;

class ProjectPolicy
{
    use ChecksProjectAccess;

    /** لیست پروژه‌ها برای همه باز است؛ محتوایش با scopeVisibleTo فیلتر می‌شود */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->canAccessProject($user, $project->id);
    }

    public function create(User $user): bool
    {
        return $user->can(Ability::ProjectsCreate->value);
    }

    /** ویرایش پروژه و مدیریت اعضا، فازها، اسپرینت‌ها و لینک‌ها */
    public function update(User $user, Project $project): bool
    {
        return $user->can(Ability::ProjectsCreate->value)
            && $this->canAccessProject($user, $project->id);
    }

    public function archive(User $user, Project $project): bool
    {
        return $user->can(Ability::ProjectsArchive->value)
            && $this->canAccessProject($user, $project->id);
    }

    /** حذف کامل فقط برای مدیر (از طریق Gate::before) */
    public function delete(User $user, Project $project): bool
    {
        return false;
    }
}
