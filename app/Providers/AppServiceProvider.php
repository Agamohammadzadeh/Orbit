<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {

        // در توسعه: lazy loading، پر کردن فیلد ناموجود و... خطا می‌دهند (جلوگیری از N+1)
        Model::shouldBeStrict(! $this->app->isProduction());

        // در production دستورهای مخرب (migrate:fresh و...) غیرفعال
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // تاریخ‌ها immutable و با زبان برنامه
        Date::use(CarbonImmutable::class);
        Carbon::setLocale(config('app.locale'));

        // سیاست رمز عبور
        Password::defaults(fn () => Password::min(8)
            ->letters()
            ->numbers()
            ->uncompromised(app()->isProduction() ? 3 : 0));
    }
}
