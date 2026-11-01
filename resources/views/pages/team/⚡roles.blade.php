<?php

use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\SaveRole;
use App\Enums\Ability;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new #[Title('نقش‌ها و دسترسی‌ها')] class extends Component {
    /* نقش در حال ویرایش (یا حالت «نقش جدید») */
    public ?int $selectedId = null;

    public bool $creating = false;

    public string $name = '';

    /** @var array<int,string> */
    public array $abilities = [];

    /* پیام بالای صفحه */
    public ?string $flash = null;

    public bool $flashIsError = false;

    public int $flashId = 0;

    public function mount(): void
    {
        Gate::authorize('users.manage');

        $first = $this->roles->first();

        if ($first) {
            $this->select($first->id);
        }
    }

    /* ---------------- داده‌ها ---------------- */

    #[Computed]
    public function roles()
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->withCount('users')
            ->with('permissions')
            ->get()
            ->sortBy(fn ($r) => match ($r->name) {
                'admin' => '0',
                'member' => '1',
                default => '2-'.$r->name,
            })
            ->values();
    }

    #[Computed]
    public function selectedRole()
    {
        return $this->creating ? null : $this->roles->firstWhere('id', $this->selectedId);
    }

    private function notify(string $message, bool $error = false): void
    {
        $this->flash = $message;
        $this->flashIsError = $error;
        $this->flashId++;
    }

    /* ---------------- انتخاب و ساخت ---------------- */

    public function select(int $id): void
    {
        $role = $this->roles->firstWhere('id', $id);

        if (! $role) {
            return;
        }

        $this->resetValidation();
        $this->creating = false;
        $this->selectedId = $role->id;
        $this->name = $role->name;
        $this->abilities = $role->permissions->pluck('name')->values()->all();
    }

    public function startCreate(): void
    {
        $this->resetValidation();
        $this->creating = true;
        $this->selectedId = null;
        $this->name = '';
        $this->abilities = [];
    }

    public function selectAll(): void
    {
        $this->abilities = Ability::values();
    }

    public function clearAll(): void
    {
        $this->abilities = [];
    }

    /* ---------------- ذخیره ---------------- */

    public function save(): void
    {
        Gate::authorize('users.manage');

        $role = $this->creating ? null : Role::findOrFail($this->selectedId);

        $this->validate(
            [
                'name' => ['required', 'string', 'min:2', 'max:50'],
                'abilities' => ['array'],
                'abilities.*' => [Rule::in(Ability::values())],
            ],
            [
                'name.required' => 'نام نقش را وارد کن.',
                'name.min' => 'نام نقش باید حداقل :min حرف باشد.',
                'name.max' => 'نام نقش نباید بیشتر از :max حرف باشد.',
                'abilities.*.in' => 'یکی از دسترسی‌ها معتبر نیست.',
            ],
        );

        $wasCreating = $this->creating;

        try {
            $saved = (new SaveRole)->handle($role, $this->name, $this->abilities);
        } catch (DomainException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        unset($this->roles, $this->selectedRole);

        $this->select($saved->id);
        $this->notify($wasCreating ? 'نقش جدید ساخته شد.' : 'تغییرات نقش ذخیره شد.');
    }

    /* ---------------- حذف ---------------- */

    public function deleteRole(int $id): void
    {
        Gate::authorize('users.manage');

        $role = Role::findOrFail($id);

        try {
            (new DeleteRole)->handle($role);
        } catch (DomainException $e) {
            $this->notify($e->getMessage(), error: true);

            return;
        }

        unset($this->roles, $this->selectedRole);

        $first = $this->roles->first();
        $first ? $this->select($first->id) : $this->startCreate();

        $this->notify('نقش حذف شد.');
    }
};
?>

@php
    $roles = $this->roles;
    $selected = $this->selectedRole;
    $isAdminRole = $selected?->name === UserRole::Admin->value;
    $isSystem = $selected && UserRole::isSystem($selected->name);
    $groups = Ability::grouped();
    $total = count(Ability::cases());
@endphp

<div class="space-y-6">
    {{-- سربرگ --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">نقش‌ها و دسترسی‌ها</h1>
            <p class="mt-1 text-sm text-muted">تعیین می‌کنی هر نقش چه کارهایی می‌تواند انجام بدهد.</p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('team') }}" wire:navigate class="btn-secondary">
                <x-ui.icon name="users" class="size-4.5" />
                اعضای تیم
            </a>
            <button type="button" wire:click="startCreate" class="btn-primary">
                <x-ui.icon name="plus" class="size-4.5" />
                نقش جدید
            </button>
        </div>
    </div>

    <x-ui.flash :message="$flash" :error="$flashIsError" wire:key="flash-{{ $flashId }}" />

    <div class="grid items-start gap-6 lg:grid-cols-3">
        {{-- فهرست نقش‌ها --}}
        <div class="card p-2">
            <ul class="space-y-1">
                @if ($creating)
                    <li>
                        <div class="flex items-center gap-3 rounded-control border border-primary/40 bg-primary-soft px-3 py-3">
                            <x-ui.icon name="plus" class="size-5 text-primary" />
                            <span class="text-sm font-medium text-primary">نقش جدید</span>
                        </div>
                    </li>
                @endif

                @foreach ($roles as $r)
                    @php $active = ! $creating && $selectedId === $r->id; @endphp
                    <li wire:key="role-{{ $r->id }}">
                        <button
                            type="button"
                            wire:click="select({{ $r->id }})"
                            @if ($active) aria-current="true" @endif
                            @class([
                                'flex w-full items-center gap-3 rounded-control border px-3 py-3 text-start transition',
                                'border-primary/40 bg-primary-soft' => $active,
                                'border-transparent hover:bg-line/40' => ! $active,
                            ])
                        >
                            <x-ui.icon
                                :name="$r->name === 'admin' ? 'lock' : 'shield'"
                                :class="$active ? 'size-5 text-primary' : 'size-5 text-muted'"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2">
                                    <span @class(['truncate text-sm font-medium', 'text-primary' => $active])>{{ UserRole::labelFor($r->name) }}</span>
                                    @if (UserRole::isSystem($r->name))
                                        <span class="shrink-0 rounded-full bg-line/60 px-2 py-0.5 text-[0.6875rem] text-muted">سیستمی</span>
                                    @endif
                                </span>
                                <span class="tabular mt-0.5 block text-xs text-muted">
                                    {{ $r->users_count }} کاربر
                                    ·
                                    {{ $r->name === 'admin' ? 'همه‌ی دسترسی‌ها' : $r->permissions->count().' دسترسی' }}
                                </span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- ویرایشگر --}}
        <div class="lg:col-span-2">
            @if ($creating || $selected)
                <form wire:submit="save" class="card" novalidate>
                    {{-- عنوان --}}
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-6 py-4">
                        <div class="min-w-0">
                            <h2 class="text-base font-bold">
                                {{ $creating ? 'نقش جدید' : UserRole::labelFor($selected->name) }}
                            </h2>
                            <p class="mt-0.5 text-sm text-muted">
                                @if ($creating)
                                    یک نام انتخاب کن و دسترسی‌های این نقش را تیک بزن.
                                @elseif ($isAdminRole)
                                    نقش سیستمی؛ همیشه همه‌ی دسترسی‌ها را دارد.
                                @elseif ($isSystem)
                                    نقش پیش‌فرض کاربران جدید. نامش ثابت است، ولی دسترسی‌هایش قابل تغییر است.
                                @else
                                    نقش سفارشی
                                @endif
                            </p>
                        </div>

                        @if (! $creating && ! $isSystem && $selected->users_count === 0)
                            <button
                                type="button"
                                @click="$store.confirm.ask({
                                    title: @js('حذف نقش «'.$selected->name.'»'),
                                    message: 'این نقش و دسترسی‌هایش حذف می‌شود. این کار قابل بازگشت نیست.',
                                    confirmLabel: 'حذف نقش',
                                    tone: 'danger',
                                    action: () => $wire.deleteRole({{ $selected->id }}),
                                })"
                                class="inline-flex h-9 items-center gap-2 rounded-control border border-line px-3 text-sm font-medium text-muted transition hover:border-danger/40 hover:bg-danger/10 hover:text-danger"
                            >
                                <x-ui.icon name="trash" class="size-4.5" />
                                حذف نقش
                            </button>
                        @endif
                    </div>

                    <div class="space-y-6 px-6 py-5">
                        @error('form')
                        <div class="rounded-control border border-danger/30 bg-danger/10 px-3.5 py-2.5 text-sm text-danger" role="alert">
                            {{ $message }}
                        </div>
                        @enderror

                        {{-- نام --}}
                        <div class="space-y-1.5">
                            <label for="role-name" class="text-sm font-medium">نام نقش</label>
                            <input
                                id="role-name"
                                type="text"
                                wire:model="name"
                                @disabled($isSystem)
                                class="input disabled:cursor-not-allowed disabled:opacity-70"
                                placeholder="مثلاً: طراح، پشتیبان، سرپرست تیم"
                                autocomplete="off"
                                aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                            />
                            @error('name')
                            <p class="text-sm text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- هشدار مدیر --}}
                        @if ($isAdminRole)
                            <div class="flex items-start gap-2.5 rounded-control border border-primary/30 bg-primary-soft px-3.5 py-3 text-sm text-primary">
                                <x-ui.icon name="lock" class="mt-0.5 size-4.5" />
                                <p>نقش مدیر قابل ویرایش نیست و همیشه همه‌ی دسترسی‌ها را دارد. این کار از قفل شدن بیرون از سیستم جلوگیری می‌کند.</p>
                            </div>
                        @endif

                        {{-- ماتریس دسترسی‌ها --}}
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-bold">
                                    دسترسی‌ها
                                    <span class="tabular ms-1 font-normal text-muted">{{ count($abilities) }} از {{ $total }}</span>
                                </h3>

                                @unless ($isAdminRole)
                                    <div class="flex items-center gap-3 text-sm">
                                        <button type="button" wire:click="selectAll" class="text-muted transition hover:text-primary">انتخاب همه</button>
                                        <button type="button" wire:click="clearAll" class="text-muted transition hover:text-primary">برداشتن همه</button>
                                    </div>
                                @endunless
                            </div>

                            <div class="mt-3 space-y-5">
                                @foreach ($groups as $group => $items)
                                    <div>
                                        <p class="mb-1.5 text-xs font-medium text-muted">{{ $group }}</p>
                                        <div class="divide-y divide-line overflow-hidden rounded-control border border-line">
                                            @foreach ($items as $ability)
                                                <label
                                                    wire:key="ability-{{ $ability->value }}"
                                                    @class([
                                                        'flex items-start gap-3 px-4 py-3 transition',
                                                        'hover:bg-line/30' => ! $isAdminRole,
                                                    ])
                                                >
                                                    <input
                                                        type="checkbox"
                                                        wire:model="abilities"
                                                        value="{{ $ability->value }}"
                                                        @disabled($isAdminRole)
                                                        class="mt-0.5 size-4.5 shrink-0 rounded border-line accent-primary"
                                                    />
                                                    <span class="min-w-0">
                                                        <span class="block text-sm font-medium">{{ $ability->label() }}</span>
                                                        <span class="mt-0.5 block text-xs leading-relaxed text-muted">{{ $ability->description() }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <p class="mt-4 text-xs leading-relaxed text-muted">
                                دیدن هر پروژه جدا از این‌ها، به عضویت کاربر در آن پروژه بستگی دارد؛ مگر اینکه نقش «دیدن همه‌ی پروژه‌ها» را داشته باشد.
                            </p>
                        </div>

                        {{-- کاربران این نقش --}}
                        @if (! $creating && $selected)
                            <div class="flex items-center justify-between gap-3 rounded-control border border-line bg-canvas/50 px-4 py-3 text-sm">
                                <span class="text-muted">
                                    <span class="tabular font-medium text-fg">{{ $selected->users_count }}</span> کاربر با این نقش
                                </span>
                                @if ($selected->users_count > 0)
                                    <a href="{{ route('team', ['role' => $selected->name]) }}" wire:navigate class="text-primary transition hover:underline">مشاهده‌ی کاربران</a>
                                @else
                                    <span class="text-xs text-muted">هنوز به کسی داده نشده</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- دکمه‌ها --}}
                    @unless ($isAdminRole)
                        <div class="flex items-center justify-end gap-2.5 rounded-b-panel border-t border-line bg-canvas/50 px-6 py-4">
                            @if ($creating)
                                <button
                                    type="button"
                                    wire:click="select({{ $roles->first()?->id ?? 0 }})"
                                    class="btn-secondary"
                                >انصراف</button>
                            @else
                                <button type="button" wire:click="select({{ $selected->id }})" class="btn-secondary">بازنشانی</button>
                            @endif

                            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                                <svg wire:loading wire:target="save" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                                </svg>
                                {{ $creating ? 'ساخت نقش' : 'ذخیره‌ی تغییرات' }}
                            </button>
                        </div>
                    @endunless
                </form>
            @else
                <div class="card px-6 py-14 text-center">
                    <p class="font-medium">نقشی انتخاب نشده</p>
                    <p class="mt-1 text-sm text-muted">یک نقش را از فهرست انتخاب کن یا نقش جدید بساز.</p>
                </div>
            @endif
        </div>
    </div>
</div>
