<?php

namespace App\Support;

use App\Enums\NotificationType;
use App\Models\User;

class NotificationPreferences
{
    /** @return array{email_enabled:bool,app:array<string,bool>,email:array<string,bool>} */
    public static function defaults(): array
    {
        $types = collect(NotificationType::cases());

        return [
            'email_enabled' => true,
            'app' => $types->mapWithKeys(fn ($t) => [$t->value => true])->all(),
            'email' => $types->mapWithKeys(fn ($t) => [$t->value => $t->defaultEmail()])->all(),
        ];
    }

    /** تنظیمات ذخیره‌شده‌ی کاربر، با پیش‌فرض‌ها برای کلیدهای ناموجود @return array{email_enabled:bool,app:array<string,bool>,email:array<string,bool>} */
    public static function get(User $user): array
    {
        $stored = $user->notification_settings ?? [];
        $d = self::defaults();

        $merge = fn (string $key) => array_merge(
            $d[$key],
            array_intersect_key(array_map('boolval', (array) ($stored[$key] ?? [])), $d[$key])
        );

        return [
            'email_enabled' => (bool) ($stored['email_enabled'] ?? $d['email_enabled']),
            'app' => $merge('app'),
            'email' => $merge('email'),
        ];
    }

    /** $channel: app | email (برای ایمیل، کلید اصلی هم باید روشن باشد) */
    public static function wants(User $user, NotificationType $type, string $channel): bool
    {
        $p = self::get($user);

        return $channel === 'email'
            ? $p['email_enabled'] && $p['email'][$type->value]
            : $p['app'][$type->value];
    }

    /**
     * @param  array<string,mixed>  $app
     * @param  array<string,mixed>  $email
     */
    public static function save(User $user, bool $emailEnabled, array $app, array $email): void
    {
        $d = self::defaults();
        $clean = fn (array $in, array $keys) => collect($keys)->mapWithKeys(fn ($_, $k) => [$k => (bool) ($in[$k] ?? false)])->all();

        $user->forceFill(['notification_settings' => [
            'email_enabled' => $emailEnabled,
            'app' => $clean($app, $d['app']),
            'email' => $clean($email, $d['email']),
        ]])->save();
    }
}
