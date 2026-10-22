<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Done = 'done';

    public function label(): string
    {
        return config("orbit.project_statuses.{$this->value}.label", $this->value);
    }

    public function color(): string
    {
        return config("orbit.project_statuses.{$this->value}.color", '#8B8BA3');
    }
}
