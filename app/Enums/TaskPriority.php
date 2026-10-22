<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return config("orbit.priorities.{$this->value}.label", $this->value);
    }

    public function color(): string
    {
        return config("orbit.priorities.{$this->value}.color", '#8B8BA3');
    }

    /** وزن عددی برای مرتب‌سازی (بزرگ‌تر = مهم‌تر) */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }
}
