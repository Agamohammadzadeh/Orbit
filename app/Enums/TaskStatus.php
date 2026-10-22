<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Done = 'done';

    public function label(): string
    {
        return config("orbit.kanban.columns.{$this->value}.label", $this->value);
    }

    public function color(): string
    {
        return config("orbit.kanban.columns.{$this->value}.color", '#8B8BA3');
    }

    public function isDone(): bool
    {
        return $this === self::Done;
    }

    /** مقادیر رشته‌ای همه‌ی وضعیت‌ها به ترتیب ستون‌های کانبان */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
