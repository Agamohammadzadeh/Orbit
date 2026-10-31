<?php

namespace Database\Seeders;

use App\Models\Label;
use Illuminate\Database\Seeder;

class LabelSeeder extends Seeder
{
    /** برچسب‌های اولیه از config/orbit.php (قابل اجرای چندباره) */
    public function run(): void
    {
        foreach (config('orbit.default_labels', []) as $label) {
            Label::query()->firstOrCreate(
                ['name' => $label['name']],
                ['color' => $label['color']],
            );
        }
    }
}
