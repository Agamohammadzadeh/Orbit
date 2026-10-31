<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('ورود')] class extends Component {
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'ایمیل را وارد کن.',
            'email.email' => 'ایمیل معتبر نیست.',
            'password.required' => 'رمز عبور را وارد کن.',
        ];
    }

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        // فقط کاربر فعال وارد می‌شود (کاربر غیرفعال همان پیام عمومی را می‌گیرد)
        $attempt = Auth::attempt([
            'email' => $this->email,
            'password' => $this->password,
            'is_active' => true,
        ], $this->remember);

        if (! $attempt) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form' => 'ایمیل یا رمز عبور درست نیست.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form' => "تعداد تلاش‌ها زیاد بود. بعد از {$seconds} ثانیه دوباره امتحان کن.",
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
};
?>

<div class="animate-pop-in">
    {{-- تغییر تم روشن/تیره --}}
    <button
        type="button"
        x-data
        @click="$store.theme.set(document.documentElement.classList.contains('dark') ? 'light' : 'dark')"
        class="fixed end-4 top-4 flex size-10 items-center justify-center rounded-control border border-line bg-surface text-muted transition hover:text-fg"
        aria-label="تغییر تم"
    >
        {{-- ماه (در حالت روشن) --}}
        <svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
        </svg>
        {{-- خورشید (در حالت تیره) --}}
        <svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5" />
            <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
        </svg>
    </button>

    <div class="mb-8 flex justify-center">
        <x-ui.logo size="44" />
    </div>

    <div class="card p-6 sm:p-8">
        <h1 class="text-xl font-bold tracking-tight">ورود به حساب</h1>
        <p class="mt-1 text-sm text-muted">برای ادامه، وارد فضای کاری تیم شو.</p>

        <form wire:submit="login" class="mt-6 space-y-5" novalidate>
            {{-- خطای کلی: رمز اشتباه یا قفل موقت --}}
            @error('form')
            <div class="rounded-control border border-danger/30 bg-danger/10 px-3.5 py-2.5 text-sm text-danger" role="alert">
                {{ $message }}
            </div>
            @enderror

            {{-- ایمیل --}}
            <div class="space-y-1.5">
                <label for="email" class="text-sm font-medium">ایمیل</label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    dir="ltr"
                    class="input text-start"
                    placeholder="name@company.com"
                    autocomplete="username"
                    inputmode="email"
                    autofocus
                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                />
                @error('email')
                <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{-- رمز عبور --}}
            <div class="space-y-1.5" x-data="{ show: false }">
                <label for="password" class="text-sm font-medium">رمز عبور</label>
                <div class="relative">
                    <input
                        id="password"
                        :type="show ? 'text' : 'password'"
                        wire:model="password"
                        dir="ltr"
                        class="input pe-11! text-start"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                    />
                    <button
                        type="button"
                        @click="show = !show"
                        class="absolute inset-y-0 start-0 flex w-11 items-center justify-center text-muted transition hover:text-fg"
                        :aria-label="show ? 'پنهان کردن رمز' : 'نمایش رمز'"
                    >
                        <svg x-show="!show" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg x-show="show" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22" />
                        </svg>
                    </button>
                </div>
                @error('password')
                <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{-- مرا به خاطر بسپار --}}
            <label class="flex cursor-pointer select-none items-center gap-2 text-sm text-muted">
                <input type="checkbox" wire:model="remember" class="size-4 rounded border-line accent-primary" />
                مرا به خاطر بسپار
            </label>

            {{-- دکمه‌ی ورود --}}
            <button type="submit" class="btn-primary w-full cursor-pointer" wire:loading.attr="disabled" wire:target="login">
                <svg wire:loading wire:target="login" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                <span wire:loading.remove wire:target="login">ورود</span>
                <span wire:loading wire:target="login">در حال ورود…</span>
            </button>
        </form>
    </div>

    <p class="mt-6 text-center text-sm text-muted">
        حساب نداری یا رمزت را فراموش کرده‌ای؟ از مدیر تیم بخواه.
    </p>
</div>
