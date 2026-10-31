<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    private const NAMES = [
        'سایت شرکتی آریان', 'فروشگاه آنلاین مهتاب', 'پرتال آموزشی دانش', 'لندینگ کمپین بهاره',
        'پنل مدیریت سپهر', 'وب‌سایت کلینیک سلامت',
    ];

    private const COLORS = ['#5B5BD6', '#12A594', '#D6409F', '#F5A524', '#3E8EF7', '#FF7A59'];

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 months', '-1 week');

        return [
            'client_id' => Client::factory(),
            'owner_id' => User::factory()->admin(),
            'name' => fake()->randomElement(self::NAMES),
            // slug خالی بماند: مدل خودش یکتا می‌سازد
            'description' => fake()->optional(0.8)->randomElement([
                'طراحی و پیاده‌سازی کامل سایت، شامل پنل مدیریت و سئوی پایه.',
                'بازطراحی رابط کاربری و بهبود سرعت و تجربه‌ی موبایل.',
                'راه‌اندازی فروشگاه آنلاین با درگاه پرداخت و مدیریت موجودی.',
            ]),
            'status' => ProjectStatus::Active,
            'color' => fake()->randomElement(self::COLORS),
            'start_date' => $start,
            'end_date' => fake()->dateTimeBetween('+2 weeks', '+4 months'),
            'archived_at' => null,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => ProjectStatus::Paused]);
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Done,
            'end_date' => now()->subWeeks(2),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Done,
            'archived_at' => now()->subWeeks(3),
        ]);
    }
}
