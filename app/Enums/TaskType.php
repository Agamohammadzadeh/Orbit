<?php

namespace App\Enums;

enum TaskType: string
{
    case Feature = 'feature';
    case Bug = 'bug';
    case Improvement = 'improvement';
    case Design = 'design';
    case Content = 'content';

    public function label(): string
    {
        return config("orbit.task_types.{$this->value}.label", $this->value);
    }

    public function color(): string
    {
        return config("orbit.task_types.{$this->value}.color", '#5B5BD6');
    }
}

