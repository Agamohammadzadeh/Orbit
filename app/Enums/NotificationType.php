<?php

namespace App\Enums;

use App\Notifications\DeadlineApproachingNotification;
use App\Notifications\MentionedInCommentNotification;
use App\Notifications\NewCommentNotification;
use App\Notifications\TaskAssignedNotification;

enum NotificationType: string
{
    case TaskAssigned = 'task_assigned';
    case Mention = 'mention';
    case Comment = 'comment';
    case Deadline = 'deadline';

    public function label(): string
    {
        return match ($this) {
            self::TaskAssigned => 'ارجاع تسک',
            self::Mention => 'منشن',
            self::Comment => 'کامنت جدید',
            self::Deadline => 'یادآوری ددلاین',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TaskAssigned => 'وقتی تسکی به تو داده می‌شود.',
            self::Mention => 'وقتی در یک کامنت منشن می‌شوی.',
            self::Comment => 'وقتی روی تسک‌هایی که مسئول یا سازنده‌شان هستی کامنت گذاشته می‌شود.',
            self::Deadline => 'وقتی مهلت یکی از تسک‌های بازت نزدیک است.',
        };
    }

    /** آیکون (از x-ui.icon) */
    public function icon(): string
    {
        return match ($this) {
            self::TaskAssigned => 'user-plus',
            self::Mention => 'at-sign',
            self::Comment => 'message',
            self::Deadline => 'clock',
        };
    }

    /** ایمیل برای کاربر جدید به‌صورت پیش‌فرض روشن باشد؟ (کامنت‌ها پرتعدادند، پس نه) */
    public function defaultEmail(): bool
    {
        return $this !== self::Comment;
    }

    /** کلاس اعلان؛ برای فیلتر فهرست بر اساس ستون type جدول notifications */
    public function notificationClass(): string
    {
        return match ($this) {
            self::TaskAssigned => TaskAssignedNotification::class,
            self::Mention => MentionedInCommentNotification::class,
            self::Comment => NewCommentNotification::class,
            self::Deadline => DeadlineApproachingNotification::class,
        };
    }
}
