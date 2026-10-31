<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public const TITLES = [
        'feature' => [
            'پیاده‌سازی صفحه‌ی ورود و ثبت‌نام', 'افزودن فیلتر قیمت به لیست محصولات',
            'ساخت پنل کاربری مشتری', 'اتصال درگاه پرداخت', 'پیاده‌سازی جستجوی پیشرفته',
            'ساخت سیستم نظرات و امتیازدهی', 'افزودن چندزبانه‌سازی سایت', 'ساخت داشبورد مدیریتی',
        ],
        'bug' => [
            'رفع باگ نمایش منو در موبایل', 'خطای ثبت سفارش با کد تخفیف',
            'رفع مشکل آپلود تصویر در Safari', 'اصلاح محاسبه‌ی مالیات در سبد خرید',
            'رفع باگ چیدمان RTL در فرم تماس', 'حل مشکل کندی بارگذاری صفحه‌ی اصلی',
        ],
        'improvement' => [
            'بهینه‌سازی سرعت بارگذاری تصاویر', 'بهبود سئوی صفحات محصول',
            'کاهش حجم بسته‌ی جاوااسکریپت', 'بازنویسی کوئری‌های سنگین گزارش',
            'بهبود دسترس‌پذیری فرم‌ها',
        ],
        'design' => [
            'طراحی صفحه‌ی اصلی در Figma', 'طراحی کیت اجزای رابط کاربری',
            'طراحی نسخه‌ی موبایل صفحه‌ی محصول', 'بازطراحی لوگو و هویت بصری',
            'طراحی صفحه‌ی ۴۰۴ و صفحات خطا',
        ],
        'content' => [
            'نوشتن متن صفحه‌ی درباره‌ی ما', 'تهیه‌ی تصاویر محصولات', 'نوشتن متن‌های سئو',
            'ترجمه‌ی محتوا به انگلیسی', 'ورود اطلاعات اولیه‌ی محصولات',
        ],
    ];

    private const DESCRIPTIONS = [
        'جزئیات و معیارهای پذیرش با مشتری هماهنگ شده است. قبل از شروع، طرح را در Figma ببینید.',
        'این مورد روی بقیه‌ی بخش‌ها اثر می‌گذارد؛ لطفاً قبل از ادغام، روی محیط staging تست شود.',
        'نسخه‌ی موبایل و حالت RTL هم باید بررسی شود.',
        'اولویت با تحویل نسخه‌ی ساده است؛ بهینه‌سازی‌ها بعداً انجام می‌شود.',
    ];

    public function definition(): array
    {
        $type = fake()->randomElement(TaskType::cases());
        $status = fake()->randomElement($this->weightedStatuses());
        $createdAt = fake()->dateTimeBetween('-30 days', '-2 days');

        return [
            'project_id' => Project::factory(),
            'phase_id' => null,
            'sprint_id' => null,
            'creator_id' => User::factory(),
            'assignee_id' => null,
            'title' => fake()->randomElement(self::TITLES[$type->value]),
            'description' => fake()->optional(0.7)->randomElement(self::DESCRIPTIONS),
            'status' => $status,
            'priority' => fake()->randomElement($this->weightedPriorities()),
            'type' => $type,
            // بعضی ددلاین‌ها گذشته‌اند تا گزارش «عقب‌افتاده‌ها» داده داشته باشد
            'due_date' => fake()->optional(0.8)->dateTimeBetween('-12 days', '+35 days')?->format('Y-m-d'),
            'estimate_minutes' => fake()->optional(0.7)->randomElement([60, 120, 180, 240, 360, 480, 720, 960]),
            // position خالی؛ مدل ته ستون قرار می‌دهد
            'completed_at' => $status === TaskStatus::Done
                ? fake()->dateTimeBetween($createdAt, 'now')
                : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    public function status(TaskStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'completed_at' => $status === TaskStatus::Done ? now()->subDays(fake()->numberBetween(0, 10)) : null,
        ]);
    }

    public function done(): static
    {
        return $this->status(TaskStatus::Done);
    }

    public function ofType(TaskType $type): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'title' => fake()->randomElement(self::TITLES[$type->value]),
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn () => ['priority' => TaskPriority::Urgent]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::InProgress,
            'completed_at' => null,
            'due_date' => today()->subDays(fake()->numberBetween(1, 9))->toDateString(),
        ]);
    }

    /** @return array<int,TaskStatus> */
    private function weightedStatuses(): array
    {
        return [
            ...array_fill(0, 6, TaskStatus::Todo),
            ...array_fill(0, 5, TaskStatus::InProgress),
            ...array_fill(0, 2, TaskStatus::InReview),
            ...array_fill(0, 7, TaskStatus::Done),
        ];
    }

    /** @return array<int,TaskPriority> */
    private function weightedPriorities(): array
    {
        return [
            ...array_fill(0, 2, TaskPriority::Low),
            ...array_fill(0, 5, TaskPriority::Medium),
            ...array_fill(0, 3, TaskPriority::High),
            TaskPriority::Urgent,
        ];
    }
}
