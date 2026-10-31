<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * مدیر اولیه؛ مقدارها از config('orbit.admin') می‌آیند (نه مستقیم env)
     * تا با config:cache هم کار کند. قابل اجرای چندباره است.
     */
    public function run(): void
    {
        $email = config('orbit.admin.email');
        $password = config('orbit.admin.password');

        if (blank($password)) {
            if (app()->isProduction()) {
                $this->command?->error('ORBIT_ADMIN_PASSWORD در .env تنظیم نشده؛ مدیر ساخته نشد.');

                return;
            }

            $password = 'password';
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('orbit.admin.name'),
                'password' => $password, // cast ‏hashed خودش هش می‌کند
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("مدیر آماده است: {$email}");
    }
}
