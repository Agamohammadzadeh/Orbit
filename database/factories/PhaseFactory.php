<?php

namespace Database\Factories;

use App\Models\Phase;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phase>
 */
class PhaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->randomElement(['تحلیل و نیازسنجی', 'طراحی UI/UX', 'فرانت‌اند', 'بک‌اند', 'تست', 'تحویل']),
            'position' => 0,
        ];
    }
}
