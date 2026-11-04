<?php

namespace App\Actions\Roles;

use App\Enums\Ability;
use App\Enums\UserRole;
use DomainException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SaveRole
{
    /**
     * ساخت یا ویرایش نقش.
     *
     * @param  array<int,string>  $abilities  مقدارهای Ability (دسترسی‌های ناشناخته نادیده گرفته می‌شوند)
     *
     * @throws DomainException
     */
    public function handle(?Role $role, string $name, array $abilities): Role
    {
        $name = trim($name);
        $abilities = array_values(array_intersect($abilities, Ability::values()));

        if ($name === '') {
            throw new DomainException('نام نقش را وارد کن.');
        }

        if ($role?->name === UserRole::Admin->value) {
            throw new DomainException('نقش مدیر همیشه همه‌ی دسترسی‌ها را دارد و قابل ویرایش نیست.');
        }

        if ($role && UserRole::isSystem($role->name) && $role->name !== $name) {
            throw new DomainException('نام نقش‌های سیستمی قابل تغییر نیست.');
        }

        // نام‌های رزروشده (admin، member) را نقش دیگری نمی‌تواند بگیرد
        if (UserRole::isSystem($name) && $role?->name !== $name) {
            throw new DomainException('این نام برای نقش‌های سیستمی رزرو شده است.');
        }

        $taken = Role::query()
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->when($role, fn ($q) => $q->whereKeyNot($role->getKey()))
            ->exists();

        if ($taken) {
            throw new DomainException('نقشی با این نام قبلاً ساخته شده است.');
        }

        return DB::transaction(function () use ($role, $name, $abilities) {
            foreach ($abilities as $ability) {
                Permission::findOrCreate($ability, 'web');
            }

            $role ??= Role::create(['name' => $name, 'guard_name' => 'web']);

            if ($role->name !== $name) {
                $role->update(['name' => $name]);
            }

            $role->syncPermissions($abilities);

            return $role;
        });
    }
}
