<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    public const BODIES = [
        'طرح نهایی رو در Figma آپدیت کردم، لطفاً نگاه کنید.',
        'روی staging تست شد و مشکلی نبود.',
        'این مورد به تأیید مشتری نیاز داره، منتظر جوابشون هستم.',
        'یه باگ جزئی تو نسخه‌ی موبایل دیدم، الان رفعش می‌کنم.',
        'کد رو push کردم، برای بازبینی آماده‌ست.',
        'به نظرم بهتره این بخش رو ساده‌تر کنیم.',
        'ددلاین رو یه روز جلو بردم چون به API وابسته‌ست.',
        'ممنون، همین‌طور پیش بریم.',
    ];

    public function definition(): array
    {
        $createdAt = fake()->dateTimeBetween('-20 days', 'now');

        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'body' => fake()->randomElement(self::BODIES),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
