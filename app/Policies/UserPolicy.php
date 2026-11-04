<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

/**
 * مدیریت کاربران. حفاظت‌های «آخرین مدیر» و «تغییر خود» داخل Actionهاست (UpdateUser)؛
 * اینجا فقط تعیین می‌شود چه کسی اجازه‌ی ورود به این بخش را دارد.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Ability::UsersManage->value);
    }

    public function view(User $user, User $target): bool
    {
        return $this->viewAny($user) || $user->is($target);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->viewAny($user);
    }

    /** کاربر حذف نمی‌شود، غیرفعال می‌شود (تاریخچه‌ی کارها حفظ شود) */
    public function delete(User $user, User $target): bool
    {
        return false;
    }
}
