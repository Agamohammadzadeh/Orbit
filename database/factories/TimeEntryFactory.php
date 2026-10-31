<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'minutes' => fake()->randomElement([30, 45, 60, 90, 120, 180, 240]),
            'worked_on' => fake()->dateTimeBetween('-14 days', 'now')->format('Y-m-d'),
            'note' => fake()->optional(0.4)->randomElement([
                'پیاده‌سازی اولیه', 'رفع ایرادات بازبینی', 'جلسه با مشتری', 'تست و رفع باگ',
            ]),
        ];
    }
}
