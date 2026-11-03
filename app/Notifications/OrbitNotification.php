<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Notification;

/**
 * پایه‌ی همه‌ی اعلان‌های Orbit. فقط کانال دیتابیس است؛
 * ایمیل و چک ترجیح کاربر در App\Support\Notifier انجام می‌شود (از Notifier::send استفاده کن).
 */
abstract class OrbitNotification extends Notification
{
    abstract public function type(): NotificationType;

    /** متن فارسی اعلان */
    abstract public function message(): string;

    /** مسیر نسبی مقصد (مثلاً /tasks/12) */
    abstract public function path(): string;

    /** @return array<string,mixed> */
    protected function extra(): array
    {
        return [];
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'message' => $this->message(),
            'url' => $this->path(),
        ] + $this->extra();
    }
}
