<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        // نام کوتاه برای رابطه‌های polymorphic (Link، Activity Log)
        Relation::enforceMorphMap([
            'project' => Project::class,
            'task' => Task::class,
            'user' => User::class,
        ]);

        // نقش «مدیر» از همه‌ی Gateها و Policyها رد می‌شود.
        // (برای بقیه null برمی‌گردد تا بررسی عادی ادامه پیدا کند)
        Gate::before(fn (User $user, string $ability) => $user->isAdmin() ? true : null);

        // سیاست رمز عبور
        Password::defaults(fn () => Password::min(8)
            ->letters()
            ->numbers()
            ->uncompromised(app()->isProduction() ? 3 : 0));
    }
}
