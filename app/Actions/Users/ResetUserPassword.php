<?php

namespace App\Actions\Users;

use App\Models\User;

class ResetUserPassword
{
    /** مدیر رمز جدید را تعیین می‌کند؛ کاربر از همه‌ی دستگاه‌ها خارج می‌شود. */
    public function handle(User $target, string $password): void
    {
        $target->forceFill(['password' => $password])->save(); // cast ‏hashed
        $target->terminateSessions();
    }
}
