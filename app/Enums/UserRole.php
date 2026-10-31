<?php

namespace App\Enums;

/**
 * نام نقش‌های سیستمی. دیگر cast روی کاربر نیست؛ نقش‌ها در جدول roles ذخیره می‌شوند.
 * - admin: همه‌ی دسترسی‌ها، قابل ویرایش و حذف نیست.
 * - member: نقش پیش‌فرض کاربر جدید؛ دسترسی‌هایش قابل ویرایش است ولی نام و حذفش نه.
 * نقش‌های ساخته‌شده توسط مدیر نام آزاد دارند (مثلاً «مدیر پروژه»).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدیر',
            self::Member => 'عضو تیم',
        };
    }

    public static function isSystem(string $name): bool
    {
        return self::tryFrom($name) !== null;
    }

    /** برچسب نمایشی هر نقش؛ برای نقش‌های سفارشی خود نام است */
    public static function labelFor(?string $name): string
    {
        if ($name === null) {
            return '—';
        }

        return self::tryFrom($name)?->label() ?? $name;
    }
}
