<?php

use App\Enums\Ability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('پروفایل من')]
class extends Component {
    use WithFileUploads;

    public string $name = '';

    public $avatar = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $status = null;

    public function mount(): void
    {
        $this->name = auth()->user()->name;
    }

    /* ---------------- نام ---------------- */

    public function saveProfile(): void
    {
        $data = $this->validate(
            ['name' => ['required', 'string', 'min:2', 'max:80']],
            [
                'name.required' => 'نام را وارد کن.',
                'name.min' => 'نام باید حداقل :min حرف باشد.',
                'name.max' => 'نام نباید بیشتر از :max حرف باشد.',
            ],
        );

        auth()->user()->update(['name' => trim($data['name'])]);

        // هدر هم باید نام جدید را نشان بدهد؛ پس صفحه دوباره رندر می‌شود
        session()->flash('status', 'نام ذخیره شد.');
        $this->redirectRoute('settings.profile', navigate: true);
    }

    /* ---------------- عکس پروفایل ---------------- */

    /** با انتخاب فایل، همان لحظه اعتبارسنجی و ذخیره می‌شود */
    public function updatedAvatar(): void
    {
        try {
            $this->validate(
                ['avatar' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:' . config('orbit.avatars.max_kb')]],
                [
                    'avatar.image' => 'فایل باید تصویر باشد.',
                    'avatar.mimes' => 'فقط JPG، PNG و WebP قبول است.',
                    'avatar.max' => 'حجم عکس نباید بیشتر از :max کیلوبایت باشد.',
                    'avatar.uploaded' => 'آپلود عکس انجام نشد. دوباره امتحان کن.',
                ],
            );
        } catch (ValidationException $e) {
            $this->reset('avatar');

            throw $e;
        }

        $user = auth()->user();
        $disk = config('orbit.avatars.disk');

        $path = $this->avatar->store('avatars', $disk);

        if ($user->avatar_path) {
            Storage::disk($disk)->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => $path]);
        $this->reset('avatar');

        session()->flash('status', 'عکس پروفایل به‌روز شد.');
        $this->redirectRoute('settings.profile', navigate: true);
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();

        if ($user->avatar_path) {
            Storage::disk(config('orbit.avatars.disk'))->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        session()->flash('status', 'عکس پروفایل حذف شد.');
        $this->redirectRoute('settings.profile', navigate: true);
    }

    /* ---------------- رمز عبور ---------------- */

    public function updatePassword(): void
    {
        $this->status = null;

        $this->validate(
            [
                'current_password' => ['required', 'current_password'],
                'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
            ],
            [
                'current_password.required' => 'رمز فعلی را وارد کن.',
                'current_password.current_password' => 'رمز فعلی درست نیست.',
                'password.required' => 'رمز جدید را وارد کن.',
                'password.confirmed' => 'تکرار رمز با رمز جدید یکی نیست.',
                'password.different' => 'رمز جدید باید با رمز فعلی فرق داشته باشد.',
                'password.min' => 'رمز جدید باید حداقل :min کاراکتر باشد.',
                'password.letters' => 'رمز جدید باید حداقل یک حرف داشته باشد.',
                'password.numbers' => 'رمز جدید باید حداقل یک عدد داشته باشد.',
                'password.uncompromised' => 'این رمز در نشت‌های اطلاعاتی دیده شده؛ رمز دیگری انتخاب کن.',
            ],
        );

        $user = auth()->user();
        $user->update(['password' => $this->password]); // cast ‏hashed خودش هش می‌کند

        // از بقیه‌ی دستگاه‌ها خارج شو (سشن فعلی می‌ماند)
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', session()->getId())
                ->delete();
        }

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->status = 'رمز عبور تغییر کرد.';
    }
};
?>

@php
    $user = auth()->user();
    $message = $status ?? session('status');

    $date = function ($value) {
        if (! $value) {
            return '—';
        }

        return config('orbit.calendar') === 'jalali'
            ? \Morilog\Jalali\Jalalian::fromDateTime($value)->format('Y/m/d')
            : $value->format('Y/m/d');
    };
@endphp

<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">پروفایل من</h1>
        <p class="mt-1 text-sm text-muted">اطلاعات حساب، رمز عبور و ظاهر برنامه.</p>
    </div>

    {{-- پیام موفقیت --}}
    @if ($message)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 4000)"
            x-show="show"
            x-transition.opacity
            class="flex items-center gap-2.5 rounded-control border border-success/30 bg-success/10 px-3.5 py-2.5 text-sm text-success"
            role="status"
        >
            <x-ui.icon name="check" class="size-4.5"/>
            {{ $message }}
        </div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-3">
        {{-- ستون اول: خلاصه‌ی حساب و دسترسی‌ها --}}
        <div class="space-y-6">
            <div class="card p-6 text-center">
                <div class="mx-auto w-fit">
                    <x-ui.avatar :user="$user" :size="96"/>
                </div>

                <div class="mt-4 flex items-center justify-center gap-2">
                    {{-- تغییر عکس --}}
                    <label
                        class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-control border border-line px-3 text-sm font-medium transition focus-within:ring-2 focus-within:ring-primary hover:border-primary/40 hover:text-primary">
                        <x-ui.icon name="camera" class="size-4.5" wire:loading.remove wire:target="avatar"/>
                        <svg wire:loading wire:target="avatar" class="size-4 animate-spin" viewBox="0 0 24 24"
                             fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/>
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"
                                  stroke-linecap="round"/>
                        </svg>
                        {{ $user->avatar_path ? 'تغییر عکس' : 'افزودن عکس' }}
                        <input type="file" wire:model="avatar" accept="image/png,image/jpeg,image/webp"
                               class="sr-only"/>
                    </label>

                    {{-- حذف عکس (فقط وقتی عکس دارد) --}}
                    @if ($user->avatar_path)
                        <button
                            type="button"
                            @click="$store.confirm.ask({
                                title: 'حذف عکس پروفایل',
                                message: 'عکست حذف می‌شود و به‌جایش حروف اول نامت نشان داده می‌شود.',
                                confirmLabel: 'حذف عکس',
                                tone: 'danger',
                                action: () => $wire.removeAvatar(),
                            })"
                            class="inline-flex h-9 items-center gap-2 rounded-control border border-line px-3 text-sm font-medium text-muted transition hover:border-danger/40 hover:bg-danger/10 hover:text-danger cursor-pointer"
                        >
                            <x-ui.icon name="trash" class="size-4.5"/>
                            حذف
                        </button>
                    @endif
                </div>

                @error('avatar')
                <p class="mt-3 text-sm text-danger">{{ $message }}</p>
                @enderror

                <p class="mt-2 text-xs text-muted">JPG، PNG یا WebP،
                    حداکثر {{ (int) (config('orbit.avatars.max_kb') / 1024) }} مگابایت</p>

                <h2 class="mt-4 text-lg font-bold">{{ $user->name }}</h2>
                <span
                    class="mt-1.5 inline-block rounded-full bg-primary-soft px-2.5 py-0.5 text-xs font-medium text-primary">{{ $user->roleLabel() }}</span>
                <div dir="ltr" class="mt-2 truncate text-sm text-muted">{{ $user->email }}</div>

                <dl class="mt-5 space-y-2.5 border-t border-line pt-5 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">عضو از</dt>
                        <dd class="tabular font-medium">{{ $date($user->created_at) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">آخرین بازدید</dt>
                        <dd class="font-medium">{{ $user->last_seen_at?->diffForHumans() ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- دسترسی‌های من (فقط‌خواندنی) --}}
            <div class="card p-6">
                <h2 class="text-base font-bold">دسترسی‌های من</h2>
                <p class="mt-0.5 text-sm text-muted">این دسترسی‌ها را مدیر تیم تعیین می‌کند.</p>

                <ul class="mt-4 space-y-2.5">
                    @foreach (\App\Enums\Ability::cases() as $ability)
                        @php $allowed = $user->can($ability->value); @endphp
                        <li class="flex items-center gap-2.5 text-sm {{ $allowed ? '' : 'text-muted' }}">
                            @if ($allowed)
                                <x-ui.icon name="check" class="size-4.5 text-success"/>
                            @else
                                <x-ui.icon name="x" class="size-4.5 text-muted/60"/>
                            @endif
                            {{ $ability->label() }}
                        </li>
                    @endforeach
                </ul>

                <p class="mt-4 border-t border-line pt-4 text-xs leading-relaxed text-muted">
                    دیدن پروژه‌ها جدا از این‌ها، به عضویتت در هر پروژه بستگی دارد.
                </p>
            </div>
        </div>

        {{-- ستون دوم و سوم: فرم‌ها --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- اطلاعات حساب --}}
            <form wire:submit="saveProfile" class="card space-y-5 p-6" novalidate>
                <div>
                    <h2 class="text-base font-bold">اطلاعات حساب</h2>
                    <p class="mt-0.5 text-sm text-muted">نامی که همکارانت می‌بینند.</p>
                </div>

                <div class="space-y-1.5">
                    <label for="name" class="text-sm font-medium">نام</label>
                    <input
                        id="name"
                        type="text"
                        wire:model="name"
                        class="input"
                        autocomplete="name"
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                    />
                    @error('name')
                    <p class="text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="email" class="text-sm font-medium">ایمیل</label>
                    <input
                        id="email"
                        type="email"
                        value="{{ $user->email }}"
                        dir="ltr"
                        disabled
                        class="input text-start disabled:cursor-not-allowed disabled:opacity-70"
                    />
                    <p class="text-xs text-muted">برای تغییر ایمیل از مدیر تیم بخواه.</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveProfile">
                        <svg wire:loading wire:target="saveProfile" class="size-4 animate-spin" viewBox="0 0 24 24"
                             fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/>
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"
                                  stroke-linecap="round"/>
                        </svg>
                        ذخیره
                    </button>
                </div>
            </form>

            {{-- رمز عبور --}}
            <form wire:submit="updatePassword" class="card space-y-5 p-6" x-data="{ show: false }" novalidate>
                <div>
                    <h2 class="text-base font-bold">تغییر رمز عبور</h2>
                    <p class="mt-0.5 text-sm text-muted">رمز جدید باید حداقل ۸ کاراکتر و شامل حرف و عدد باشد. بعد از
                        تغییر، از بقیه‌ی دستگاه‌ها خارج می‌شوی.</p>
                </div>

                <div class="space-y-1.5">
                    <label for="current_password" class="text-sm font-medium">رمز فعلی</label>
                    <input
                        id="current_password"
                        :type="show ? 'text' : 'password'"
                        wire:model="current_password"
                        dir="ltr"
                        class="input text-start"
                        autocomplete="current-password"
                        aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}"
                    />
                    @error('current_password')
                    <p class="text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="password" class="text-sm font-medium">رمز جدید</label>
                        <input
                            id="password"
                            :type="show ? 'text' : 'password'"
                            wire:model="password"
                            dir="ltr"
                            class="input text-start"
                            autocomplete="new-password"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                        />
                        @error('password')
                        <p class="text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="password_confirmation" class="text-sm font-medium">تکرار رمز جدید</label>
                        <input
                            id="password_confirmation"
                            :type="show ? 'text' : 'password'"
                            wire:model="password_confirmation"
                            dir="ltr"
                            class="input text-start"
                            autocomplete="new-password"
                        />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex cursor-pointer select-none items-center gap-2 text-sm text-muted">
                        <input type="checkbox" x-model="show" class="size-4 rounded border-line accent-primary"/>
                        نمایش رمزها
                    </label>

                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="updatePassword">
                        <svg wire:loading wire:target="updatePassword" class="size-4 animate-spin" viewBox="0 0 24 24"
                             fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/>
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"
                                  stroke-linecap="round"/>
                        </svg>
                        تغییر رمز
                    </button>
                </div>
            </form>

            {{-- ظاهر برنامه --}}
            <div class="card space-y-4 p-6" x-data>
                <div>
                    <h2 class="text-base font-bold">ظاهر برنامه</h2>
                    <p class="mt-0.5 text-sm text-muted">فقط روی همین دستگاه ذخیره می‌شود.</p>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    @foreach ([
                        'light' => ['روشن', 'sun'],
                        'dark' => ['تیره', 'moon'],
                        'system' => ['سیستم', 'monitor'],
                    ] as $mode => [$label, $icon])
                        <button
                            type="button"
                            @click="$store.theme.set('{{ $mode }}')"
                            :class="$store.theme.mode === '{{ $mode }}'
                                ? 'border-primary bg-primary-soft text-primary'
                                : 'border-line text-muted hover:text-fg'"
                            class="flex h-11 items-center justify-center gap-2 rounded-control border text-sm font-medium transition"
                        >
                            <x-ui.icon :name="$icon" class="size-4.5"/>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
