<?php

use App\Models\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('مشتری‌ها')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** name | recent | projects */
    #[Url]
    public string $sort = 'name';

    /* فرم */
    public bool $showForm = false;

    public ?int $clientId = null;

    public string $name = '';

    public string $contactName = '';

    public string $email = '';

    public string $phone = '';

    public string $notes = '';

    /* پیام */
    public ?string $flash = null;

    public bool $flashIsError = false;

    public int $flashId = 0;

    public function mount(): void
    {
        Gate::authorize('viewAny', Client::class);

        if (! in_array($this->sort, ['name', 'recent', 'projects'], true)) {
            $this->sort = 'name';
        }
    }

    /* ---------------- داده‌ها ---------------- */

    #[Computed]
    public function total(): int
    {
        return Client::query()->count();
    }

    #[Computed]
    public function clients()
    {
        $term = str_replace(['%', '_', '\\'], '', trim($this->search));

        $query = Client::query()
            ->when($term !== '', fn ($q) => $q->where(
                fn ($w) => $w->whereLike('name', "%{$term}%")
                    ->orWhereLike('contact_name', "%{$term}%")
                    ->orWhereLike('email', "%{$term}%")
                    ->orWhereLike('phone', "%{$term}%")
            ))
            ->withCount([
                'projects as projects_count' => fn ($p) => $p->whereNull('archived_at'),
                'projects as active_count' => fn ($p) => $p->whereNull('archived_at')->where('status', 'active'),
            ]);

        match ($this->sort) {
            'recent' => $query->latest('id'),
            'projects' => $query->orderByDesc('projects_count')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        return $query->paginate(12);
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'sort'], true)) {
            $this->resetPage();
        }
    }

    private function notify(string $message, bool $error = false): void
    {
        $this->flash = $message;
        $this->flashIsError = $error;
        $this->flashId++;
    }

    /* ---------------- ساخت و ویرایش ---------------- */

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['clientId', 'name', 'contactName', 'email', 'phone', 'notes']);
    }

    public function create(): void
    {
        Gate::authorize('create', Client::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $client = Client::query()->findOrFail($id);
        Gate::authorize('update', $client);

        $this->resetForm();
        $this->clientId = $client->id;
        $this->name = $client->name;
        $this->contactName = (string) $client->contact_name;
        $this->email = (string) $client->email;
        $this->phone = (string) $client->phone;
        $this->notes = (string) $client->notes;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $client = $this->clientId ? Client::query()->findOrFail($this->clientId) : null;

        $client ? Gate::authorize('update', $client) : Gate::authorize('create', Client::class);

        $this->validate(
            [
                'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('clients', 'name')->ignore($this->clientId)],
                'contactName' => ['nullable', 'string', 'max:120'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9۰-۹+\-\s()]+$/u'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            [
                'name.required' => 'نام مشتری را وارد کن.',
                'name.min' => 'نام باید حداقل :min حرف باشد.',
                'name.max' => 'نام نباید بیشتر از :max حرف باشد.',
                'name.unique' => 'مشتری‌ای با این نام قبلاً ثبت شده است.',
                'contactName.max' => 'نام رابط نباید بیشتر از :max حرف باشد.',
                'email.email' => 'ایمیل معتبر نیست.',
                'phone.regex' => 'شماره‌ی تماس فقط می‌تواند عدد، فاصله و + - ( ) داشته باشد.',
                'phone.max' => 'شماره‌ی تماس خیلی طولانی است.',
                'notes.max' => 'یادداشت نباید بیشتر از :max حرف باشد.',
            ],
        );

        $blank = fn (string $v) => trim($v) !== '' ? trim($v) : null;

        $data = [
            'name' => trim($this->name),
            'contact_name' => $blank($this->contactName),
            'email' => $blank($this->email),
            'phone' => $blank($this->phone),
            'notes' => $blank($this->notes),
        ];

        $isEdit = $client !== null;

        $isEdit ? $client->update($data) : Client::query()->create($data);

        unset($this->clients, $this->total);
        $this->closeForm();
        $this->notify($isEdit ? 'اطلاعات مشتری ذخیره شد.' : 'مشتری جدید ساخته شد.');
    }

    /* ---------------- حذف ---------------- */

    public function delete(int $id): void
    {
        $client = Client::query()->findOrFail($id);
        Gate::authorize('delete', $client);

        $name = $client->name;
        $client->delete();   // پروژه‌ها حذف نمی‌شوند؛ client_id آن‌ها خالی می‌شود (nullOnDelete)

        unset($this->clients, $this->total);
        $this->notify("مشتری «{$name}» حذف شد.");
    }
};
?>

@php
    $clients = $this->clients;
    $hasFilters = $search !== '';
    $sortOptions = ['name' => 'نام', 'recent' => 'جدیدترین', 'projects' => 'بیشترین پروژه'];
    $palette = ['#5B5BD6', '#12A594', '#D6409F', '#F5A524', '#3E8EF7', '#FF7A59', '#E5484D', '#8B8BA3'];
@endphp

<div class="space-y-6">
    {{-- سربرگ --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">مشتری‌ها</h1>
            <p class="mt-1 text-sm text-muted"><span class="tabular">{{ $this->total }}</span> مشتری</p>
        </div>

        <button type="button" wire:click="create" class="btn-primary">
            <x-ui.icon name="plus" class="size-4.5" />
            مشتری جدید
        </button>
    </div>

    <x-ui.flash :message="$flash" :error="$flashIsError" wire:key="flash-{{ $flashId }}" />

    {{-- فیلترها --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative min-w-56 flex-1 sm:max-w-sm">
            <x-ui.icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4.5 -translate-y-1/2 text-muted" />
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                class="input ps-10!"
                placeholder="جستجوی نام، رابط، ایمیل یا تلفن…"
                aria-label="جستجو"
            />
        </div>

        <x-ui.select wire:model.live="sort" :options="$sortOptions" label="مرتب‌سازی" class="w-44" />

        @if ($hasFilters)
            <button type="button" wire:click="$set('search', '')" class="text-sm text-muted transition hover:text-primary">پاک کردن جستجو</button>
        @endif
    </div>

    @if ($clients->isEmpty())
        <div class="card">
            @if ($hasFilters)
                <x-ui.empty-state title="مشتری‌ای پیدا نشد" icon="search">عبارت جستجو را تغییر بده.</x-ui.empty-state>
            @else
                <x-ui.empty-state title="هنوز مشتری‌ای نیست" icon="briefcase">
                    مشتری‌ها را بساز تا هر پروژه به مشتری خودش وصل شود.
                </x-ui.empty-state>
            @endif
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($clients as $c)
                <article wire:key="client-{{ $c->id }}" class="card flex flex-col p-5">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl text-lg font-bold text-white"
                            style="background-color: {{ $palette[$c->id % count($palette)] }}"
                        >{{ mb_substr($c->name, 0, 1) }}</span>

                        <div class="min-w-0 flex-1">
                            <h2 class="truncate font-bold">{{ $c->name }}</h2>
                            <p class="mt-0.5 text-xs text-muted">
                                @if ($c->projects_count > 0)
                                    <span class="tabular">{{ $c->projects_count }}</span> پروژه
                                    @if ($c->active_count > 0)
                                        · <span class="tabular text-success">{{ $c->active_count }}</span> فعال
                                    @endif
                                @else
                                    بدون پروژه
                                @endif
                            </p>
                        </div>

                        <x-ui.dropdown width="w-44">
                            <x-slot:trigger>
                                <button
                                    type="button"
                                    @click="toggle()"
                                    :aria-expanded="open"
                                    aria-haspopup="menu"
                                    class="flex size-8 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg"
                                    aria-label="عملیات {{ $c->name }}"
                                >
                                    <x-ui.icon name="more" class="size-5" />
                                </button>
                            </x-slot:trigger>

                            <x-ui.dropdown-item icon="edit" wire:click="edit({{ $c->id }})">ویرایش</x-ui.dropdown-item>
                            <x-ui.dropdown-item :href="route('projects.index', ['q' => $c->name])" icon="folder">پروژه‌ها</x-ui.dropdown-item>
                            <button
                                type="button"
                                role="menuitem"
                                @click="$store.confirm.ask({
                                    title: @js('حذف «'.$c->name.'»'),
                                    message: @js($c->projects_count > 0
                                        ? 'مشتری حذف می‌شود. '.$c->projects_count.' پروژه‌ی او حذف نمی‌شوند و «بدون مشتری» می‌مانند.'
                                        : 'این مشتری حذف می‌شود. این کار قابل بازگشت نیست.'),
                                    confirmLabel: 'حذف مشتری',
                                    tone: 'danger',
                                    action: () => $wire.delete({{ $c->id }}),
                                })"
                                class="flex h-9 w-full items-center gap-2.5 rounded-control px-3 text-start text-sm text-danger transition hover:bg-danger/10 focus:outline-none focus-visible:bg-danger/10"
                            >
                                <x-ui.icon name="trash" class="size-4.5" />
                                حذف
                            </button>
                        </x-ui.dropdown>
                    </div>

                    <ul class="mt-4 space-y-2 text-sm">
                        @if ($c->contact_name)
                            <li class="flex items-center gap-2.5">
                                <x-ui.icon name="user" class="size-4 shrink-0 text-muted" />
                                <span class="truncate">{{ $c->contact_name }}</span>
                            </li>
                        @endif
                        @if ($c->email)
                            <li class="flex items-center gap-2.5">
                                <x-ui.icon name="mail" class="size-4 shrink-0 text-muted" />
                                <a href="mailto:{{ $c->email }}" dir="ltr" class="truncate transition hover:text-primary">{{ $c->email }}</a>
                            </li>
                        @endif
                        @if ($c->phone)
                            <li class="flex items-center gap-2.5">
                                <x-ui.icon name="phone" class="size-4 shrink-0 text-muted" />
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $c->phone) }}" dir="ltr" class="tabular truncate transition hover:text-primary">{{ $c->phone }}</a>
                            </li>
                        @endif
                        @if (! $c->contact_name && ! $c->email && ! $c->phone)
                            <li class="text-muted">اطلاعات تماسی ثبت نشده.</li>
                        @endif
                    </ul>

                    @if ($c->notes)
                        <p class="mt-4 line-clamp-2 border-t border-line pt-4 text-xs leading-relaxed text-muted">{{ $c->notes }}</p>
                    @endif
                </article>
            @endforeach
        </div>

        @if ($clients->hasPages())
            <div class="card"><x-ui.pagination :paginator="$clients" /></div>
        @endif
    @endif

    {{-- مودال ساخت و ویرایش --}}
    @if ($showForm)
        <form wire:submit="save" novalidate>
            <x-ui.modal
                :title="$clientId ? 'ویرایش مشتری' : 'مشتری جدید'"
                :description="$clientId ? 'اطلاعات مشتری را تغییر بده.' : 'نام و اطلاعات تماس مشتری.'"
                close="closeForm"
                size="max-w-xl"
            >
                <div class="space-y-5">
                    <div class="space-y-1.5">
                        <label for="client-name" class="text-sm font-medium">نام مشتری یا شرکت</label>
                        <input
                            id="client-name"
                            type="text"
                            wire:model="name"
                            x-init="$nextTick(() => $el.focus())"
                            class="input"
                            placeholder="مثلاً: شرکت آریان صنعت"
                            autocomplete="off"
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                        />
                        @error('name')
                        <p class="text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="client-contact" class="text-sm font-medium">نام رابط <span class="font-normal text-muted">(اختیاری)</span></label>
                        <input id="client-contact" type="text" wire:model="contactName" class="input" autocomplete="off" aria-invalid="{{ $errors->has('contactName') ? 'true' : 'false' }}" />
                        @error('contactName')
                        <p class="text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="client-email" class="text-sm font-medium">ایمیل <span class="font-normal text-muted">(اختیاری)</span></label>
                            <input
                                id="client-email"
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

                        <div class="space-y-1.5">
                            <label for="client-phone" class="text-sm font-medium">تلفن <span class="font-normal text-muted">(اختیاری)</span></label>
                            <input
                                id="client-phone"
                                type="tel"
                                wire:model="phone"
                                dir="ltr"
                                class="input text-start"
                                placeholder="09123456789"
                                autocomplete="off"
                                aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}"
                            />
                            @error('phone')
                            <p class="text-sm text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="client-notes" class="text-sm font-medium">یادداشت <span class="font-normal text-muted">(اختیاری)</span></label>
                        <textarea
                            id="client-notes"
                            wire:model="notes"
                            rows="3"
                            class="input h-auto! min-h-20 resize-y py-2.5"
                            placeholder="ساعت‌های تماس، نحوه‌ی پرداخت، ترجیحات…"
                        ></textarea>
                        @error('notes')
                        <p class="text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <x-slot:footer>
                    <button type="button" wire:click="closeForm" class="btn-secondary">انصراف</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                        <svg wire:loading wire:target="save" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        {{ $clientId ? 'ذخیره' : 'ساخت مشتری' }}
                    </button>
                </x-slot:footer>
            </x-ui.modal>
        </form>
    @endif
</div>
