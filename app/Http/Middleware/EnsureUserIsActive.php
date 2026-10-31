<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * در گروه web اجرا می‌شود (bootstrap/app.php):
 * - کاربر غیرفعال‌شده در همان درخواست بعدی خارج می‌شود.
 * - last_seen_at حداکثر هر ۵ دقیقه یک‌بار به‌روز می‌شود.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'حساب کاربری شما غیرفعال شده است. با مدیر تماس بگیر.']);
        }

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(5)))) {
            // آپدیت مستقیم؛ بدون رویداد مدل و بدون لاگ فعالیت
            User::query()->whereKey($user->id)->update(['last_seen_at' => now()]);
        }

        return $next($request);
    }
}
