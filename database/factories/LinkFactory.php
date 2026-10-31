<?php

namespace Database\Factories;

use App\Enums\LinkType;
use App\Models\Link;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * linkable_type از Morph Map می‌آید: 'task' یا 'project'.
 *
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'linkable_type' => 'task',
            'linkable_id' => Task::factory(),
            'type' => LinkType::Other,
            'title' => 'لینک مرجع',
            'url' => 'https://example.com/'.fake()->lexify('????????'),
        ];
    }

    public function figma(): static
    {
        return $this->state(fn () => [
            'type' => LinkType::Figma,
            'title' => 'طرح Figma',
            'url' => 'https://www.figma.com/file/'.fake()->bothify('?#?#?#?#?#').'/Orbit-Demo',
        ]);
    }

    public function repo(): static
    {
        return $this->state(fn () => [
            'type' => LinkType::Repo,
            'title' => 'ریپازیتوری',
            'url' => 'https://github.com/orbit-demo/'.fake()->lexify('site-????'),
        ]);
    }

    public function staging(): static
    {
        return $this->state(fn () => [
            'type' => LinkType::Staging,
            'title' => 'سایت Staging',
            'url' => 'https://staging-'.fake()->lexify('????').'.orbit-demo.test',
        ]);
    }

    /** لینک را به یک پروژه یا تسک وصل می‌کند: ->attachedTo($project) */
    public function attachedTo(\Illuminate\Database\Eloquent\Model $model): static
    {
        return $this->state(fn () => [
            'linkable_type' => $model->getMorphClass(),
            'linkable_id' => $model->getKey(),
        ]);
    }
}
