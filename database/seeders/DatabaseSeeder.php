<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // داده‌های ضروری (در production هم اجرا می‌شوند). ترتیب مهم است: نقش‌ها قبل از مدیر
        $this->call([
            RolePermissionSeeder::class,
            LabelSeeder::class,
            AdminUserSeeder::class,
        ]);

        // داده‌ی نمونه فقط در محیط توسعه و تست
        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
