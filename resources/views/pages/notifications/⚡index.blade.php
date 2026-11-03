<?php

use App\Enums\NotificationType;
use App\Mail\OrbitNotificationMail;
use App\Support\NotificationPreferences;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('اعلان‌ها')] class extends Component {
    use WithPagination;

    /** list | settings */
    #[Url(except: 'list')]
    public string $tab = 'list';

    /** all | unread */
    #[Url(except: 'all')]
    public string $filter = 'all';

    #[Url(except: '')]
    public string $type = '';

    /* تنظیمات */
    public bool $emailEnabled = true;

    /** @var array<string,bool> */
    public array $app = [];

    /** @var array<string,bool> */
    public array $email = [];

    public ?string $flash = null;

    public bool $flashIsError = false;

    public int $flashId = 0;

    public function mount(): void
    {
        if (! in_array($this->tab, ['list', 'settings'], true)) {
            $this->tab = 'list';
        }
        if (! in_array($this->filter, ['all', 'unread'], true)) {
            $this->filter = 'all';
        }
        if (NotificationType::tryFrom($this->type) === null) {
            $this->type = '';
        }

        $this->loadSettings();
    }

    private function loadSettings(): void
    {
        $p = NotificationPreferences::get(auth()->user());

        $this->emailEnabled = $p['email_enabled'];
        $this->app = $p['app'];
        $this->email = $p['email'];
    }

    private function notify(string $message, bool $error = false): void
    {
        $this->flash = $message;
        $this->flashIsError = $error;
        $this->flashId++;
    }

    private function changed(): void
    {
        unset($this->items, $this->unread);
        $this->dispatch('notifications-updated');
    }

    /* ---------------- فهرست ---------------- */

    #[Computed]
    public function items()
    {
        return auth()->user()->notifications()
            ->when($this->filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when(NotificationType::tryFrom($this->type), fn ($q, $t) => $q->where('type', $t->notificationClass()))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function unread(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['filter', 'type'], true)) {
            $this->resetPage();
        }
    }

    /** اعلان را خوانده می‌کند و به مقصدش می‌رود */
    public function open(string $id): void
    {
        $n = auth()->user()->notifications()->whereKey($id)->first();

        if (! $n) {
            return;
        }

        $n->markAsRead();
        $this->dispatch('notifications-updated');

        $url = $n->data['url'] ?? null;
        $safe = is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('notifications');

        $this->redirect($safe, navigate: true);
    }

    public function toggleRead(string $id): void
    {
        $n = auth()->user()->notifications()->whereKey($id)->first();

        if ($n) {
            $n->read_at ? $n->markAsUnread() : $n->markAsRead();
            $this->changed();
        }
    }

    public function delete(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->delete();
        $this->changed();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->changed();
    }

    public function clearRead(): void
    {
        auth()->user()->readNotifications()->delete();
        $this->resetPage();
        $this->changed();
        $this->notify('اعلان‌های خوانده‌شده پاک شدند.');
    }

    /* ---------------- تنظیمات ---------------- */

    public function saveSettings(): void
    {
        $this->validate(
            [
                'emailEnabled' => ['boolean'],
                'app' => ['array'],
                'app.*' => ['boolean'],
                'email' => ['array'],
                'email.*' => ['boolean'],
            ],
            ['*.boolean' => 'مقدار تنظیمات معتبر نیست.'],
        );

        NotificationPreferences::save(auth()->user(), $this->emailEnabled, $this->app, $this->email);

        $this->notify('تنظیمات اعلان‌ها ذخیره شد.');
    }

    /** فقط فرم را به پیش‌فرض برمی‌گرداند؛ با «ذخیره» ثبت می‌شود */
    public function resetDefaults(): void
    {
        $d = NotificationPreferences::defaults();

        $this->emailEnabled = $d['email_enabled'];
        $this->app = $d['app'];
        $this->email = $d['email'];
    }

    public function sendTest(): void
    {
        $user = auth()->user();
        $key = 'test-mail:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->notify('چند ایمیل آزمایشی پشت‌سرهم فرستادی. چند دقیقه بعد دوباره امتحان کن.', error: true);

            return;
        }

        RateLimiter::hit($key, 300);

        try {
            // sendNow: همان لحظه و بدون صف، تا خطای تنظیمات ایمیل دیده شود
            Mail::to($user->email)->sendNow(new OrbitNotificationMail(
                mailSubject: config('app.name').': ایمیل آزمایشی',
                text: 'اگر این ایمیل را می‌بینی، تنظیمات ایمیل Orbit درست کار می‌کند.',
                url: route('notifications', ['tab' => 'settings']),
            ));
        } catch (Throwable $e) {
            report($e);
            $this->notify('ارسال ایمیل ناموفق بود. تنظیمات ایمیل سرور را به مدیر بگو.', error: true);

            return;
        }

        $this->notify(
            config('mail.default') === 'log'
                ? 'ایمیل آزمایشی ساخته شد، ولی سرور روی حالت آزمایشی (log) است و ایمیل واقعی نمی‌فرستد.'
                : "ایمیل آزمایشی به {$user->email} ارسال شد."
        );
    }
};
?>

@php
    $me = auth()->user();
    $items = $this->items;
    $unread = $this->unread;
    $types = NotificationType::cases();

    $typeOptions = ['' => 'همه‌ی انواع'];
    foreach ($types as $t) {
        $typeOptions[$t->value] = $t->label();
    }

    $hasFilters = $filter !== 'all' || $type !== '';

    // گروه‌بندی صفحه‌ی جاری بر اساس روز
    $groups = $items->getCollection()->groupBy(fn ($n) => match (true) {
        $n->created_at->isToday() => 'امروز',
        $n->created_at->isYesterday() => 'دیروز',
        default => \App\Support\Dates::short($n->created_at),
    });

    $tabs = ['list' => 'اعلان‌ها', 'settings' => 'تنظیمات'];
@endphp

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">اعلان‌ها</h1>
            <p class="mt-1 text-sm text-muted">
                @if ($unread > 0)
                    <span class="tabular font-medium text-fg">{{ $unread }}</span> اعلان خوانده‌نشده
                @else
                    همه‌ی اعلان‌ها خوانده شده‌اند.
                @endif
            </p>
        </div>

        @if ($tab === 'list')
            <div class="flex flex-wrap items-center gap-2.5">
                @if ($unread > 0)
                    <button type="button" wire:click="markAllRead" class="btn-secondary h-10! px-4! text-sm!">
                        <x-ui.icon name="check" class="size-4" />
                        خوانده شد (همه)
                    </button>
                @endif
                <button
                    type="button"
                    @click="$store.confirm.ask({
                        title: 'پاک کردن خوانده‌شده‌ها',
                        message: 'همه‌ی اعلان‌های خوانده‌شده برای همیشه پاک می‌شوند.',
                        confirmLabel: 'پاک کن',
                        tone: 'danger',
                        action: () => $wire.clearRead(),
                    })"
                    class="btn-secondary h-10! px-4! text-sm!"
                >
                    <x-ui.icon name="trash" class="size-4" />
                    پاک کردن خوانده‌شده‌ها
                </button>
            </div>
        @endif
    </div>

    <x-ui.flash :message="$flash" :error="$flashIsError" wire:key="flash-{{ $flashId }}" />

    {{-- تب‌ها --}}
    <div class="flex gap-1 border-b border-line" role="tablist">
        @foreach ($tabs as $key => $text)
            <button
                type="button"
                role="tab"
                wire:click="$set('tab', '{{ $key }}')"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                @class([
                    '-mb-px h-11 border-b-2 px-4 text-sm font-medium transition',
                    'border-primary text-primary' => $tab === $key,
                    'border-transparent text-muted hover:text-fg' => $tab !== $key,
                ])
            >{{ $text }}</button>
        @endforeach
    </div>

    @if ($tab === 'settings')
        {{-- ========== تنظیمات ========== --}}
        <form wire:submit="saveSettings" class="space-y-6" novalidate>
            {{-- کلید اصلی ایمیل --}}
            <div class="card flex items-center justify-between gap-4 p-6">
                <div class="flex min-w-0 items-start gap-4">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-control bg-primary-soft text-primary">
                        <x-ui.icon name="mail" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold">دریافت اعلان‌ها با ایمیل</h2>
                        <p class="mt-0.5 text-sm text-muted">
                            ایمیل‌ها به <span dir="ltr" class="font-medium text-fg">{{ $me->email }}</span> فرستاده می‌شود.
                            اگر خاموشش کنی، هیچ ایمیلی نمی‌گیری و اعلان‌ها فقط داخل برنامه می‌آیند.
                        </p>
                    </div>
                </div>

                <x-ui.switch wire:model.live="emailEnabled" aria-label="دریافت اعلان‌ها با ایمیل" />
            </div>

            {{-- ماتریس نوع اعلان --}}
            <div class="card">
                <div class="grid grid-cols-[minmax(0,1fr)_4.5rem_4.5rem] items-center gap-4 border-b border-line px-6 py-3 text-xs font-medium text-muted sm:grid-cols-[minmax(0,1fr)_6rem_6rem]">
                    <div>کدام اعلان‌ها برایم بیاید؟</div>
                    <div class="text-center">در برنامه</div>
                    <div class="text-center">ایمیل</div>
                </div>

                <ul class="divide-y divide-line">
                    @foreach ($types as $t)
                        <li wire:key="pref-{{ $t->value }}" class="grid grid-cols-[minmax(0,1fr)_4.5rem_4.5rem] items-center gap-4 px-6 py-4 sm:grid-cols-[minmax(0,1fr)_6rem_6rem]">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-line/60 text-muted">
                                    <x-ui.icon :name="$t->icon()" class="size-4" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">{{ $t->label() }}</p>
                                    <p class="mt-0.5 text-xs leading-relaxed text-muted">{{ $t->description() }}</p>
                                </div>
                            </div>

                            <div class="flex justify-center">
                                <x-ui.switch wire:model="app.{{ $t->value }}" :aria-label="$t->label().' در برنامه'" />
                            </div>

                            <div class="flex justify-center">
                                <x-ui.switch wire:model="email.{{ $t->value }}" :disabled="! $emailEnabled" :aria-label="$t->label().' با ایمیل'" />
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="flex flex-wrap items-center gap-3 border-t border-line bg-canvas/50 px-6 py-4">
                    <button type="button" wire:click="sendTest" class="btn-secondary h-10! px-4! text-sm!" wire:loading.attr="disabled" wire:target="sendTest">
                        <x-ui.icon name="send" class="size-4 rtl:-scale-x-100" />
                        ارسال ایمیل آزمایشی
                    </button>

                    <button type="button" wire:click="resetDefaults" class="text-sm text-muted transition hover:text-primary">بازگشت به پیش‌فرض</button>

                    <button type="submit" class="btn-primary ms-auto" wire:loading.attr="disabled" wire:target="saveSettings">
                        <svg wire:loading wire:target="saveSettings" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        ذخیره
                    </button>
                </div>
            </div>

            <p class="text-xs leading-relaxed text-muted">
                «در برنامه» یعنی اعلان توی زنگوله و همین صفحه دیده می‌شود. اگه خاموشش کنی آن نوع اعلان اصلاً برایت ثبت نمی‌شود.
                ایمیل‌ها ممکن است چند دقیقه دیرتر برسند، چون توی صف ارسال می‌شوند.
            </p>
        </form>
    @else
        {{-- ========== فهرست ========== --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="inline-flex rounded-control border border-line bg-surface p-1" role="group" aria-label="فیلتر اعلان‌ها">
                <button
                    type="button"
                    wire:click="$set('filter', 'all')"
                    aria-pressed="{{ $filter === 'all' ? 'true' : 'false' }}"
                    @class([
                        'h-8 rounded-md px-4 text-sm transition',
                        'bg-primary-soft font-medium text-primary' => $filter === 'all',
                        'text-muted hover:text-fg' => $filter !== 'all',
                    ])
                >همه</button>
                <button
                    type="button"
                    wire:click="$set('filter', 'unread')"
                    aria-pressed="{{ $filter === 'unread' ? 'true' : 'false' }}"
                    @class([
                        'h-8 rounded-md px-4 text-sm transition',
                        'bg-primary-soft font-medium text-primary' => $filter === 'unread',
                        'text-muted hover:text-fg' => $filter !== 'unread',
                    ])
                >خوانده‌نشده @if ($unread > 0)<span class="tabular ms-1 text-xs">({{ $unread }})</span>@endif</button>
            </div>

            <x-ui.select wire:model.live="type" :options="$typeOptions" label="فیلتر نوع" class="w-48" />

            @if ($hasFilters)
                <button type="button" wire:click="$set('filter', 'all'); $set('type', '')" class="text-sm text-muted transition hover:text-primary">پاک کردن فیلترها</button>
            @endif
        </div>

        @if ($items->isEmpty())
            <div class="card">
                @if ($hasFilters)
                    <x-ui.empty-state title="اعلانی پیدا نشد" icon="search">فیلترها را تغییر بده.</x-ui.empty-state>
                @else
                    <x-ui.empty-state title="هنوز اعلانی نداری" icon="bell">
                        ارجاع تسک، منشن، کامنت و یادآوری ددلاین اینجا نشان داده می‌شود.
                    </x-ui.empty-state>
                @endif
            </div>
        @else
            <div class="space-y-5">
                @foreach ($groups as $label => $list)
                    <section wire:key="group-{{ $label }}">
                        <h2 class="mb-2 px-1 text-xs font-bold text-muted">{{ $label }}</h2>

                        <div class="card divide-y divide-line overflow-hidden">
                            @foreach ($list as $n)
                                @php
                                    $isUnread = $n->read_at === null;
                                    $kind = NotificationType::tryFrom($n->data['type'] ?? '');
                                @endphp

                                <div wire:key="n-{{ $n->id }}" @class(['group flex items-center gap-2 pe-2', 'bg-primary-soft/40' => $isUnread])>
                                    <button
                                        type="button"
                                        wire:click="open('{{ $n->id }}')"
                                        class="flex min-w-0 flex-1 items-start gap-3 px-5 py-4 text-start transition hover:bg-line/30"
                                    >
                                        <span @class([
                                            'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full',
                                            'bg-primary-soft text-primary' => $isUnread,
                                            'bg-line/60 text-muted' => ! $isUnread,
                                        ])>
                                            <x-ui.icon :name="$kind?->icon() ?? 'bell'" class="size-4.5" />
                                        </span>

                                        <span class="min-w-0 flex-1">
                                            <span @class(['block text-sm leading-relaxed', 'font-medium' => $isUnread, 'text-muted' => ! $isUnread])>{{ $n->data['message'] ?? 'اعلان جدید' }}</span>
                                            <span class="mt-1 flex items-center gap-2 text-xs text-muted">
                                                @if ($kind)
                                                    <span class="rounded-full bg-line/60 px-2 py-0.5">{{ $kind->label() }}</span>
                                                @endif
                                                <span title="{{ \App\Support\Dates::short($n->created_at) }}">{{ $n->created_at->diffForHumans() }}</span>
                                            </span>
                                        </span>

                                        @if ($isUnread)
                                            <span class="mt-2 size-2 shrink-0 rounded-full bg-primary" aria-label="خوانده‌نشده"></span>
                                        @endif
                                    </button>

                                    <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100 max-lg:opacity-100">
                                        <button
                                            type="button"
                                            wire:click="toggleRead('{{ $n->id }}')"
                                            class="flex size-9 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg"
                                            title="{{ $isUnread ? 'علامت‌گذاری به‌عنوان خوانده‌شده' : 'علامت‌گذاری به‌عنوان خوانده‌نشده' }}"
                                            aria-label="{{ $isUnread ? 'خوانده شد' : 'خوانده‌نشده' }}"
                                        >
                                            <x-ui.icon :name="$isUnread ? 'check' : 'bell'" class="size-4.5" />
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="delete('{{ $n->id }}')"
                                            class="flex size-9 items-center justify-center rounded-control text-muted transition hover:bg-danger/10 hover:text-danger"
                                            title="حذف"
                                            aria-label="حذف اعلان"
                                        >
                                            <x-ui.icon name="trash" class="size-4.5" />
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            @if ($items->hasPages())
                <div class="card"><x-ui.pagination :paginator="$items" /></div>
            @endif
        @endif
    @endif
</div>
