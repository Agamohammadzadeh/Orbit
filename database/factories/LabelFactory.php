<?php

namespace Database\Factories;

use App\Models\Label;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'برچسب '.fake()->unique()->numberBetween(1, 99999),
            'color' => fake()->randomElement(['#5B5BD6', '#12A594', '#D6409F', '#E5484D', '#F5A524', '#F76B15']),
        ];
    }
}
