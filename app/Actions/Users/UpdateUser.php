<?php

namespace App\Actions\Users;

use App\Models\User;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateUser
{
    /**
     * ویرایش کاربر توسط مدیر؛ شامل تغییر نقش و فعال/غیرفعال کردن.
     *
     * @param  User  $actor  ?:?string,role?:string,is_active?:bool}  $data
     *
     * @throws Throwable اگر یکی از حفاظت‌ها نقض شود (پیام فارسی آماده‌ی نمایش)
     */
    public function handle(User $actor, User $target, array $data): User
    {
        $newRole = $data['role'] ?? null;
        $roleChanges = $newRole !== null && $newRole !== $target->roleName();
        $deactivating = array_key_exists('is_active', $data) && ! $data['is_active'] && $target->is_active;

        // ۱) مدیر نقش یا وضعیت حساب خودش را عوض نمی‌کند (جلوگیری از قفل‌کردن خود)
        if ($actor->is($target) && ($roleChanges || $deactivating)) {
            throw new DomainException('نمی‌توانی نقش یا وضعیت حساب خودت را تغییر بدهی.');
        }

        // ۲) آخرین مدیر فعال هیچ‌وقت تنزل یا غیرفعال نمی‌شود
        if (($roleChanges || $deactivating) && $target->isLastActiveAdmin()) {
            throw new DomainException('این آخرین مدیر فعال است و نقش یا وضعیتش قابل تغییر نیست.');
        }

        $payload = Arr::only($data, ['name', 'email']);

        if (filled($data['password'] ?? null)) {
            $payload['password'] = $data['password'];
        }

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = (bool) $data['is_active'];
        }

        DB::transaction(function () use ($target, $payload, $roleChanges, $newRole) {
            $target->update($payload);

            if ($roleChanges) {
                $target->syncRoles($newRole);
            }
        });

        // حساب غیرفعال یا رمز عوض‌شده: سشن‌های فعلی بسته می‌شوند
        // (اگر مدیر رمز خودش را عوض کرده، خودش از سیستم بیرون انداخته نمی‌شود)
        if ($deactivating || (filled($data['password'] ?? null) && ! $actor->is($target))) {
            $target->terminateSessions();
        }

        return $target->refresh();
    }
}
