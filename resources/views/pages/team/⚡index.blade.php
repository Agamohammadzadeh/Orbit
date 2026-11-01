<?php

use App\Actions\Users\CreateUser;
use App\Actions\Users\ResetUserPassword;
use App\Actions\Users\UpdateUser;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Title('اعضای تیم')] class extends Component {
    use WithPagination;

    /* فیلترها (در آدرس صفحه ذخیره می‌شوند) */
    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $status = '';

    /* فرم ساخت و ویرایش */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $formRole = 'member';

    public bool $isActive = true;

    /* ریست رمز */
    public ?int $resetId = null;

    public string $newPassword = '';

    /* پیام بالای صفحه */
    public ?string $flash = null;

    public bool $flashIsError = false;

    public int $flashId = 0;

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    /* ---------------- داده‌ها ---------------- */

    #[Computed]
    public function roles()
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->get(['id', 'name'])
            ->sortBy(fn ($r) => match ($r->name) {
                'admin' => 0,
                'member' => 1,
                default => 2,
            })
            ->values();
    }

    #[Computed]
    public function users()
    {
        $validRole = $this->role !== '' && $this->roles->contains('name', $this->role);
        $term = str_replace(['%', '_', '\\'], '', trim($this->search));

        return User::query()
            ->when($term !== '', fn ($q) => $q->where(
                fn ($w) => $w->whereLike('name', "%{$term}%")->orWhereLike('email', "%{$term}%")
            ))
            ->when($validRole, fn ($q) => $q->role($this->role))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12);
    }

    #[Computed]
    public function counts(): array
    {
        return [
            'all' => User::count(),
            'active' => User::where('is_active', true)->count(),
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    private function notify(string $message, bool $error = false): void
    {
        $this->flash = $message;
        $this->flashIsError = $error;
        $this->flashId++;
    }

    /* ---------------- ساخت و ویرایش ---------------- */

    public function create(): void
    {
        Gate::authorize('create', User::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        Gate::authorize('update', $user);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->formRole = $user->roleName() ?? UserRole::Member->value;
        $this->isActive = $user->is_active;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'email', 'password']);
        $this->formRole = UserRole::Member->value;
        $this->isActive = true;
    }

    public function save(): void
    {
        $isEdit = $this->editingId !== null;
        $target = $isEdit ? User::findOrFail($this->editingId) : null;

        $isEdit ? Gate::authorize('update', $target) : Gate::authorize('create', User::class);

        $this->validate(
            [
                'name' => ['required', 'string', 'min:2', 'max:80'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
                'password' => $isEdit ? ['nullable', Password::defaults()] : ['required', Password::defaults()],
                'formRole' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            ],
            [
                'name.required' => 'نام را وارد کن.',
                'name.min' => 'نام باید حداقل :min حرف باشد.',
                'name.max' => 'نام نباید بیشتر از :max حرف باشد.',
                'email.required' => 'ایمیل را وارد کن.',
                'email.email' => 'ایمیل معتبر نیست.',
                'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
                'password.required' => 'رمز اولیه را وارد کن.',
                'password.min' => 'رمز باید حداقل :min کاراکتر باشد.',
                'password.letters' => 'رمز باید حداقل یک حرف داشته باشد.',
                'password.numbers' => 'رمز باید حداقل یک عدد داشته باشد.',
                'password.uncompromised' => 'این رمز در نشت‌های اطلاعاتی دیده شده؛ رمز دیگری انتخاب کن.',
                'formRole.required' => 'یک نقش انتخاب کن.',
                'formRole.exists' => 'این نقش وجود ندارد.',
            ],
        );

        try {
            if ($isEdit) {
                (new UpdateUser)->handle(auth()->user(), $target, [
                    'name' => trim($this->name),
                    'email' => trim($this->email),
                    'password' => $this->password !== '' ? $this->password : null,
                    'role' => $this->formRole,
                    'is_active' => $this->isActive,
                ]);
                $message = 'اطلاعات کاربر ذخیره شد.';
            } else {
                (new CreateUser)->handle([
                    'name' => trim($this->name),
                    'email' => trim($this->email),
                    'password' => $this->password,
                    'role' => $this->formRole,
                    'is_active' => $this->isActive,
                ]);
                $message = 'کاربر جدید ساخته شد.';
            }
        } catch (DomainException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        $this->closeForm();
        $this->notify($message);
    }

    /* ---------------- فعال و غیرفعال ---------------- */

    public function toggleActive(int $id): void
    {
        $target = User::findOrFail($id);
        Gate::authorize('update', $target);

        try {
            $user = (new UpdateUser)->handle(auth()->user(), $target, ['is_active' => ! $target->is_active]);
        } catch (DomainException $e) {
            $this->notify($e->getMessage(), error: true);

            return;
        }

        $this->notify($user->is_active ? 'حساب فعال شد.' : 'حساب غیرفعال شد و از همه‌ی دستگاه‌ها خارج شد.');
    }

    /* ---------------- ریست رمز ---------------- */

    public function openReset(int $id): void
    {
        $target = User::findOrFail($id);
        Gate::authorize('update', $target);

        $this->resetValidation();
        $this->newPassword = '';
        $this->resetId = $target->id;
    }

    public function closeReset(): void
    {
        $this->resetId = null;
        $this->newPassword = '';
        $this->resetValidation();
    }

    public function saveReset(): void
    {
        $target = User::findOrFail($this->resetId);
        Gate::authorize('update', $target);

        if ($target->is(auth()->user())) {
            $this->addError('newPassword', 'رمز خودت را از صفحه‌ی «پروفایل من» تغییر بده.');

            return;
        }

        $this->validate(
            ['newPassword' => ['required', Password::defaults()]],
            [
                'newPassword.required' => 'رمز جدید را وارد کن.',
                'newPassword.min' => 'رمز باید حداقل :min کاراکتر باشد.',
                'newPassword.letters' => 'رمز باید حداقل یک حرف داشته باشد.',
                'newPassword.numbers' => 'رمز باید حداقل یک عدد داشته باشد.',
                'newPassword.uncompromised' => 'این رمز در نشت‌های اطلاعاتی دیده شده؛ رمز دیگری انتخاب کن.',
            ],
        );

        (new ResetUserPassword)->handle($target, $this->newPassword);

        $this->closeReset();
        $this->notify("رمز {$target->name} تغییر کرد و از همه‌ی دستگاه‌ها خارج شد.");
    }
};
?>

@php
    $me = auth()->user();
    $users = $this->users;
    $roles = $this->roles;
    $counts = $this->counts;
    $hasFilters = $search !== '' || $role !== '' || $status !== '';
    $roleOptions = $roles->mapWithKeys(fn ($r) => [$r->name => UserRole::labelFor($r->name)])->all();
@endphp

<div class="space-y-6">
    {{-- سربرگ --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">اعضای تیم</h1>
            <p class="mt-1 text-sm text-muted">
                <span class="tabular">{{ $counts['all'] }}</span> کاربر،
                <span class="tabular">{{ $counts['active'] }}</span> فعال
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a
                href="{{ route('team.roles') }}"
                :navigate
                class="btn-secondary"
            >
                <x-ui.icon name="shield" class="size-4.5" />
                نقش‌ها
            </a>
            <button type="button" wire:click="create" class="btn-primary">
                <x-ui.icon name="user-plus" class="size-4.5" />
                کاربر جدید
            </button>
        </div>
    </div>

    {{-- پیام --}}
    <x-ui.flash :message="$flash" :error="$flashIsError" wire:key="flash-{{ $flashId }}" />

    {{-- فیلترها --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative min-w-56 flex-1 sm:max-w-sm">
            <x-ui.icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4.5 -translate-y-1/2 text-muted" />
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                class="input ps-10!"
                placeholder="جستجوی نام یا ایمیل…"
                aria-label="جستجو"
            />
        </div>

        <x-ui.select
            wire:model.live="role"
            :options="['' => 'همه‌ی نقش‌ها'] + $roleOptions"
            label="فیلتر نقش"
            class="w-44"
        />

        <x-ui.select
            wire:model.live="status"
            :options="['' => 'همه‌ی وضعیت‌ها', 'active' => 'فعال', 'inactive' => 'غیرفعال']"
            label="فیلتر وضعیت"
            class="w-44"
        />

        @if ($hasFilters)
            <button
                type="button"
                wire:click="$set('search', ''); $set('role', ''); $set('status', '')"
                class="text-sm text-muted transition hover:text-primary"
            >پاک کردن فیلترها</button>
        @endif
    </div>

    {{-- لیست --}}    {{-- لیست --}}
    <div class="card">
        {{-- سرستون (دسکتاپ) --}}
        <div class="hidden gap-4 border-b border-line px-5 py-3 text-xs font-medium text-muted md:grid md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_7rem] lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_7rem]">
            <div>کاربر</div>
            <div>نقش</div>
            <div>وضعیت</div>
            <div class="hidden lg:block">آخرین بازدید</div>
            <div></div>
        </div>

        <ul class="divide-y divide-line">
            @forelse ($users as $u)
                @php
                    $isMe = $u->is($me);
                    $roleName = $u->roleName();
                @endphp

                <li
                    wire:key="user-{{ $u->id }}"
                    @class([
                        'flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4 md:grid md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_7rem] lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_7rem]',
                        'bg-canvas/60' => ! $u->is_active,
                    ])
                >
                    <div class="flex min-w-0 items-center gap-3 max-md:flex-1 max-md:basis-60">
                        <x-ui.avatar :user="$u" :size="40" :class="$u->is_active ? '' : 'opacity-50 grayscale'" />
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-medium">{{ $u->name }}</span>
                                @if ($isMe)
                                    <span class="shrink-0 rounded-full bg-line/60 px-2 py-0.5 text-[0.6875rem] text-muted">شما</span>
                                @endif
                            </div>
                            <div dir="ltr" class="truncate text-start text-sm text-muted">{{ $u->email }}</div>
                        </div>
                    </div>

                    <div>
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-primary-soft text-primary' => $roleName === 'admin',
                            'bg-line/60 text-fg' => $roleName !== 'admin',
                        ])>{{ $u->roleLabel() }}</span>
                    </div>

                    <div>
                        <span @class([
                            'inline-flex items-center gap-1.5 text-sm',
                            'text-success' => $u->is_active,
                            'text-muted' => ! $u->is_active,
                        ])>
                            <span @class([
                                'size-2 rounded-full',
                                'bg-success' => $u->is_active,
                                'bg-muted/50' => ! $u->is_active,
                            ])></span>
                            {{ $u->is_active ? 'فعال' : 'غیرفعال' }}
                        </span>
                    </div>

                    <div class="hidden text-sm text-muted lg:block">
                        {{ $u->last_seen_at?->diffForHumans() ?? '—' }}
                    </div>

                    {{-- عملیات --}}
                    <div class="ms-auto flex w-28 items-center justify-end gap-0.5">
                        <button
                            type="button"
                            wire:click="edit({{ $u->id }})"
                            class="flex size-9 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg"
                            title="ویرایش"
                            aria-label="ویرایش {{ $u->name }}"
                        >
                            <x-ui.icon name="edit" class="size-4.5" />
                        </button>

                        @unless ($isMe)
                            <button
                                type="button"
                                wire:click="openReset({{ $u->id }})"
                                class="flex size-9 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg"
                                title="ریست رمز"
                                aria-label="ریست رمز {{ $u->name }}"
                            >
                                <x-ui.icon name="key" class="size-4.5" />
                            </button>

                            @if ($u->is_active)
                                <button
                                    type="button"
                                    @click="$store.confirm.ask({
                                        title: @js('غیرفعال‌سازی '.$u->name),
                                        message: 'کاربر از همه‌ی دستگاه‌ها خارج می‌شود و دیگر نمی‌تواند وارد شود. تسک‌ها و کامنت‌هایش حفظ می‌شود و هر وقت بخواهی دوباره فعالش می‌کنی.',
                                        confirmLabel: 'غیرفعال کن',
                                        tone: 'danger',
                                        action: () => $wire.toggleActive({{ $u->id }}),
                                    })"
                                    class="flex size-9 items-center justify-center rounded-control text-muted transition hover:bg-danger/10 hover:text-danger"
                                    title="غیرفعال‌سازی"
                                    aria-label="غیرفعال‌سازی {{ $u->name }}"
                                >
                                    <x-ui.icon name="power" class="size-4.5" />
                                </button>
                            @else
                                <button
                                    type="button"
                                    wire:click="toggleActive({{ $u->id }})"
                                    class="flex size-9 items-center justify-center rounded-control text-muted transition hover:bg-success/10 hover:text-success"
                                    title="فعال‌سازی"
                                    aria-label="فعال‌سازی {{ $u->name }}"
                                >
                                    <x-ui.icon name="power" class="size-4.5" />
                                </button>
                            @endif
                        @endunless
                    </div>
                </li>
            @empty
                <li class="px-5 py-14 text-center">
                    <p class="font-medium">کاربری پیدا نشد</p>
                    <p class="mt-1 text-sm text-muted">
                        {{ $hasFilters ? 'فیلترها یا عبارت جستجو را تغییر بده.' : 'اولین کاربر را بساز.' }}
                    </p>
                </li>
            @endforelse
        </ul>

        {{-- صفحه‌بندی --}}
        <x-ui.pagination :paginator="$users" />
    </div>

    {{-- مودال ساخت و ویرایش --}}
    @if ($showForm)
        @php
            $isEdit = $editingId !== null;
            $isSelf = $isEdit && $editingId === $me->id;
        @endphp

        <form wire:submit="save" novalidate>
            <x-ui.modal
                :title="$isEdit ? 'ویرایش کاربر' : 'کاربر جدید'"
                :description="$isEdit ? 'اطلاعات و نقش کاربر را تغییر بده.' : 'رمز اولیه را خودت تعیین می‌کنی و به کاربر می‌دهی.'"
                close="closeForm"
            >
                <div class="space-y-5">
                    @error('form')
                    <div class="rounded-control border border-danger/30 bg-danger/10 px-3.5 py-2.5 text-sm text-danger" role="alert">
                        {{ $message }}
                    </div>
                    @enderror

                    <div class="space-y-1.5">
                        <label for="user-name" class="text-sm font-medium">نام</label>
                        <input
                            id="user-name"
                            type="text"
                            wire:model="name"
                            x-init="$nextTick(() => $el.focus())"
                            class="input"
                            autocomplete="off"
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                        />
                        @error('name')
                        <p class="text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="user-email" class="text-sm font-medium">ایمیل</label>
                        <input
                            id="user-email"
                            type="email"
                            wire:model="email"
                            dir="ltr"
                            class="input text-start"
                            placeholder="name@company.com"
                            autocomplete="off"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        />
                        @error('email')
                        <p class="text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-ui.password-field
                        id="user-password"
                        wire:model="password"
                        :label="$isEdit ? 'رمز جدید (اختیاری)' : 'رمز اولیه'"
                        :hint="$isEdit ? 'اگر خالی بماند، رمز تغییر نمی‌کند.' : 'حداقل ۸ کاراکتر، شامل حرف و عدد.'"
                        :error="$errors->first('password')"
                    />

                    <div class="space-y-1.5">
                        <label for="user-role" class="text-sm font-medium">نقش</label>
                        <x-ui.select
                            id="user-role"
                            wire:model="formRole"
                            :options="$roleOptions"
                            :disabled="$isSelf"
                        />
                        @error('formRole')
                        <p class="text-sm text-danger">{{ $message }}</p>
                        @enderror
                        @if ($isSelf)
                            <p class="text-xs text-muted">نقش و وضعیت حساب خودت را نمی‌توانی تغییر بدهی.</p>
                        @endif
                    </div>

                    <label @class([
                        'flex select-none items-center gap-2 text-sm',
                        'text-muted' => $isSelf,
                    ])>
                        <input type="checkbox" wire:model="isActive" @disabled($isSelf) class="size-4 rounded border-line accent-primary" />
                        حساب فعال است
                    </label>
                </div>

                <x-slot:footer>
                    <button type="button" wire:click="closeForm" class="btn-secondary">انصراف</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                        <svg wire:loading wire:target="save" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        {{ $isEdit ? 'ذخیره' : 'ساخت کاربر' }}
                    </button>
                </x-slot:footer>
            </x-ui.modal>
        </form>
    @endif

    {{-- مودال ریست رمز --}}
    @if ($resetId !== null)
        @php $resetTarget = $users->firstWhere('id', $resetId) ?? \App\Models\User::find($resetId); @endphp

        <form wire:submit="saveReset" novalidate>
            <x-ui.modal
                :title="'ریست رمز '.$resetTarget?->name"
                description="رمز جدید را به کاربر بده. بعد از ذخیره، از همه‌ی دستگاه‌ها خارج می‌شود."
                close="closeReset"
                size="max-w-md"
            >
                <x-ui.password-field
                    id="reset-password"
                    wire:model="newPassword"
                    label="رمز جدید"
                    hint="حداقل ۸ کاراکتر، شامل حرف و عدد. با دکمه‌ی تولید، رمز تصادفی بساز و کپی کن."
                    :error="$errors->first('newPassword')"
                />

                <x-slot:footer>
                    <button type="button" wire:click="closeReset" class="btn-secondary">انصراف</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveReset">
                        <svg wire:loading wire:target="saveReset" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        تغییر رمز
                    </button>
                </x-slot:footer>
            </x-ui.modal>
        </form>
    @endif
</div>
