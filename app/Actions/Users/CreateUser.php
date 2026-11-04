<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Throwable;

class CreateUser
{
    /**
     * مدیر کاربر جدید می‌سازد و رمز اولیه را خودش تعیین می‌کند.
     *
     * @param  array{name:string,email:string,password:string,role:string,is_active?:bool}  $data
     *
     * اعتبارسنجی (ایمیل یکتا، حداقل رمز، وجود نقش) وظیفه‌ی کامپوننت Livewire است.
     *
     * @throws Throwable
     */
    public function handle(array $data): User
    {
        // اگر نقش وجود نداشته باشد RoleDoesNotExist پرتاب می‌شود
        $role = Role::findByName($data['role'], 'web');

        return DB::transaction(function () use ($data, $role) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // cast ‏hashed خودش هش می‌کند
                'is_active' => $data['is_active'] ?? true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole($role);

            return $user->load('roles');
        });
    }
}
