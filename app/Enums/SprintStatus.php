<?php

namespace App\Enums;

enum SprintStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'برنامه‌ریزی‌شده',
            self::Active => 'فعال',
            self::Closed => 'بسته‌شده',
        };
    }
}
