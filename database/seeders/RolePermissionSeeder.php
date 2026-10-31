<?php

namespace Database\Seeders;

use App\Enums\Ability;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * دسترسی‌ها و نقش‌های پیش‌فرض. قابل اجرای چندباره است:
     * - همه‌ی دسترسی‌ها ساخته (یا حفظ) می‌شوند.
     * - نقش admin هر بار همه‌ی دسترسی‌ها را می‌گیرد.
     * - بقیه‌ی نقش‌ها فقط بار اول مقدار پیش‌فرض می‌گیرند؛ ویرایش‌های مدیر حفظ می‌شود.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Ability::cases() as $ability) {
            Permission::findOrCreate($ability->value, 'web');
        }

        foreach (config('orbit.default_roles', []) as $name => $abilities) {
            $role = Role::findOrCreate($name, 'web');

            if ($name === UserRole::Admin->value) {
                $role->syncPermissions(Ability::values());

                continue;
            }

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions(array_values(array_intersect($abilities, Ability::values())));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
