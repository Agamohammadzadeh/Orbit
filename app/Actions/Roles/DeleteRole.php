<?php

namespace App\Actions\Roles;

use App\Enums\UserRole;
use DomainException;
use Spatie\Permission\Models\Role;

class DeleteRole
{
    /** @throws DomainException */
    public function handle(Role $role): void
    {
        if (UserRole::isSystem($role->name)) {
            throw new DomainException('نقش‌های سیستمی قابل حذف نیستند.');
        }

        if ($role->users()->exists()) {
            throw new DomainException('این نقش هنوز به کاربرانی داده شده است. اول نقش آن‌ها را عوض کن.');
        }

        $role->delete();
    }
}
