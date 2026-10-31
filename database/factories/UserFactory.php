<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    private const NAMES = [
        'سارا احمدی', 'رضا کریمی', 'مریم حسینی', 'علی رستمی', 'نیلوفر صادقی',
        'امیر محمدی', 'الهام قاسمی', 'حسین نجفی', 'فاطمه جعفری', 'پویا نوری',
    ];

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(self::NAMES),
            // ایمیل ASCII ثابت (مستقل از زبان Faker)
            'email' => fake()->unique()->numerify('user#####@orbit.test'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Member,
            'is_active' => true,
            'last_seen_at' => fake()->optional(0.8)->dateTimeBetween('-3 days', 'now'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false, 'last_seen_at' => now()->subMonths(2)]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
