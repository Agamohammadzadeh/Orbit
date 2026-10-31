<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    private const COMPANIES = [
        'شرکت آریان صنعت', 'فروشگاه‌های مهتاب', 'گروه تجاری سپهر', 'آموزشگاه دانش‌پژوهان',
        'کلینیک سلامت نو', 'املاک بهار', 'استودیو معماری نقش', 'مؤسسه‌ی پارسا',
    ];

    private const CONTACTS = [
        'محمد رضایی', 'زهرا موسوی', 'کاوه شریفی', 'لیلا فرهادی', 'بهرام کاظمی', 'آزاده پناهی',
    ];

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(self::COMPANIES),
            'contact_name' => fake()->randomElement(self::CONTACTS),
            'email' => fake()->numerify('contact####@client.test'),
            'phone' => fake()->numerify('09#########'),
            'notes' => fake()->optional(0.4)->randomElement([
                'ترجیح می‌دهد هماهنگی‌ها از طریق ایمیل انجام شود.',
                'پرداخت‌ها مرحله‌ای و بعد از تأیید هر فاز است.',
                'ساعت‌های تماس: ۱۰ تا ۱۶.',
            ]),
        ];
    }
}
