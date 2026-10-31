<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // داده‌های ضروری (در production هم اجرا می‌شوند)
        $this->call([
            LabelSeeder::class,
            AdminUserSeeder::class,
        ]);

        // داده‌ی نمونه فقط در محیط توسعه و تست
        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
