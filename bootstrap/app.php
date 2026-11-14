<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // کاربر غیرفعال‌شده در همه‌ی درخواست‌های web (از جمله Livewire) بیرون انداخته می‌شود
        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);

        // مهمان‌ها به صفحه‌ی لاگین، کاربر واردشده از صفحه‌ی لاگین به داشبورد
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->hasHeader('X-Livewire') || $request->is('livewire*/update')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->isMethod('GET') && $request->is('livewire*/update')) {
                return redirect()->route('dashboard');
            }
        });
    })
    ->create();
