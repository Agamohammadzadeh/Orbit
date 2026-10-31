<?php

namespace Database\Factories;

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => 'اسپرینت '.fake()->numberBetween(1, 12),
            'goal' => fake()->optional(0.7)->randomElement([
                'تحویل نسخه‌ی اولیه‌ی صفحات اصلی',
                'رفع باگ‌های باقی‌مانده و آماده‌سازی تست',
                'پیاده‌سازی بخش پرداخت',
            ]),
            'starts_at' => today()->addDays(7),
            'ends_at' => today()->addDays(20),
            'status' => SprintStatus::Planned,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => SprintStatus::Active,
            'starts_at' => today()->subDays(7),
            'ends_at' => today()->addDays(6),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => SprintStatus::Closed,
            'starts_at' => today()->subDays(28),
            'ends_at' => today()->subDays(15),
        ]);
    }
}
