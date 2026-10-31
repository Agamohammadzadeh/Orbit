<?php

namespace Database\Factories;

use App\Models\ChecklistItem;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'title' => fake()->randomElement([
                'بررسی طرح در Figma', 'پیاده‌سازی', 'تست روی موبایل', 'بازبینی کد',
                'هماهنگی با مشتری', 'آماده‌سازی برای استقرار', 'نوشتن مستندات',
            ]),
            'is_done' => false,
            'position' => 0,
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => ['is_done' => true]);
    }
}
