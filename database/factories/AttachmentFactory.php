<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * فایل واقعی روی دیسک ساخته نمی‌شود؛ فقط رکورد (برای تست UI و لیست‌ها).
 * دانلود این رکوردها 404 می‌دهد، مگر در تست‌ها فایل را خودت بسازی.
 *
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        $file = fake()->randomElement([
            ['طرح-صفحه-اصلی.png', 'image/png'],
            ['مستندات-API.pdf', 'application/pdf'],
            ['محتوای-سئو.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['فایل-طراحی.zip', 'application/zip'],
        ]);

        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'original_name' => $file[0],
            'path' => 'demo/'.fake()->lexify('????????').'-'.fake()->numerify('####'),
            'mime_type' => $file[1],
            'size' => fake()->numberBetween(20_000, 6_000_000),
        ];
    }
}
