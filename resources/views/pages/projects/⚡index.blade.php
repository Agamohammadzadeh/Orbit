<?php

use App\Actions\Projects\SetProjectArchived;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('پروژه‌ها')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** '' | active | paused | done */
    #[Url]
    public string $status = '';

    /** active (آرشیو‌نشده) | archived */
    #[Url]
    public string $scope = 'active';

    /** recent | name | deadline */
    #[Url]
    public string $sort = 'recent';

    /** grid | list */
    #[Url]
    public string $layout = 'grid';

    public ?string $flash = null;

    public bool $flashIsError = false;

    public int $flashId = 0;

    public function mount(): void
    {
        if (! in_array($this->scope, ['active', 'archived'], true)) {
            $this->scope = 'active';
        }
        if (! in_array($this->sort, ['recent', 'name', 'deadline'], true)) {
            $this->sort = 'recent';
        }
        if (! in_array($this->layout, ['grid', 'list'], true)) {
            $this->layout = 'grid';
        }
        if (ProjectStatus::tryFrom($this->status) === null) {
            $this->status = '';
        }

        // پیام بعد از ساخت یا ویرایش (از صفحه‌ی فرم)
        if ($message = session('status')) {
            $this->notify($message);
        }
    }

    /* ---------------- داده‌ها ---------------- */

    #[Computed]
    public function counts(): array
    {
        $base = Project::query()->visibleTo(auth()->user());

        return [
            'active' => (clone $base)->notArchived()->count(),
            'archived' => (clone $base)->archived()->count(),
        ];
    }

    #[Computed]
    public function projects()
    {
        $term = str_replace(['%', '_', '\\'], '', trim($this->search));
        $status = ProjectStatus::tryFrom($this->status);

        $query = Project::query()
            ->visibleTo(auth()->user())
            ->when($this->scope === 'archived', fn ($q) => $q->archived(), fn ($q) => $q->notArchived())
            ->when($status, fn ($q) => $q->withStatus($status))
            ->when($term !== '', fn ($q) => $q->where(
                fn ($w) => $w->whereLike('name', "%{$term}%")
                    ->orWhereHas('client', fn ($c) => $c->whereLike('name', "%{$term}%"))
            ))
            ->withProgress()
            ->withCount([
                'tasks as overdue_count' => fn ($q) => $q
                    ->where('status', '!=', 'done')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', today()->toDateString()),
            ])
            ->with(['client:id,name', 'members:id,name,avatar_path']);

        match ($this->sort) {
            'name' => $query->orderBy('name'),
            'deadline' => $query->orderByRaw('end_date IS NULL')->orderBy('end_date'),
            default => $query->latest('updated_at'),
        };

        return $query->paginate(12);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope === 'archived' ? 'archived' : 'active';
        $this->resetPage();
    }

    private function notify(string $message, bool $error = false): void
    {
        $this->flash = $message;
        $this->flashIsError = $error;
        $this->flashId++;
    }

    /* ---------------- آرشیو ---------------- */

    public function archive(int $id): void
    {
        $project = Project::query()->findOrFail($id);
        Gate::authorize('archive', $project);

        (new SetProjectArchived)->handle($project, true);

        unset($this->projects, $this->counts);
        $this->notify("پروژه‌ی «{$project->name}» آرشیو شد.");
    }

    public function restore(int $id): void
    {
        $project = Project::query()->findOrFail($id);
        Gate::authorize('archive', $project);

        (new SetProjectArchived)->handle($project, false);

        unset($this->projects, $this->counts);
        $this->notify("پروژه‌ی «{$project->name}» به فهرست فعال برگشت.");
    }
};
?>

@php
    $me = auth()->user();
    $projects = $this->projects;
    $counts = $this->counts;
    $archivedView = $scope === 'archived';
    $hasFilters = $search !== '' || $status !== '';
    $grid = $layout === 'grid';

    $statusOptions = ['' => 'همه‌ی وضعیت‌ها'];
    foreach (\App\Enums\ProjectStatus::cases() as $s) {
        $statusOptions[$s->value] = $s->label();
    }
    $sortOptions = ['recent' => 'آخرین تغییر', 'name' => 'نام', 'deadline' => 'نزدیک‌ترین پایان'];
@endphp

<div class="space-y-6">
    {{-- سربرگ --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">پروژه‌ها</h1>
            <p class="mt-1 text-sm text-muted">
                <span class="tabular">{{ $counts['active'] }}</span> پروژه‌ی فعال
                @if ($counts['archived'] > 0)
                    ، <span class="tabular">{{ $counts['archived'] }}</span> آرشیو
                @endif
            </p>
        </div>

        @can('create', \App\Models\Project::class)
            <a href="{{ route('projects.create') }}" wire:navigate class="btn-primary">
                <x-ui.icon name="plus" class="size-4.5" />
                پروژه‌ی جدید
            </a>
        @endcan
    </div>

    <x-ui.flash :message="$flash" :error="$flashIsError" wire:key="flash-{{ $flashId }}" />

    {{-- فیلترها --}}
    <div class="flex flex-wrap items-center gap-3">
        {{-- فعال / آرشیو --}}
        <div class="inline-flex rounded-control border border-line bg-surface p-1" role="group" aria-label="فعال یا آرشیو">
            @foreach (['active' => 'فعال', 'archived' => 'آرشیو'] as $key => $text)
                <button
                    type="button"
                    wire:click="setScope('{{ $key }}')"
                    aria-pressed="{{ $scope === $key ? 'true' : 'false' }}"
                    @class([
                        'h-8 rounded-md px-4 text-sm transition',
                        'bg-primary-soft font-medium text-primary' => $scope === $key,
                        'text-muted hover:text-fg' => $scope !== $key,
                    ])
                >{{ $text }}</button>
            @endforeach
        </div>

        <div class="relative min-w-56 flex-1 sm:max-w-xs">
            <x-ui.icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4.5 -translate-y-1/2 text-muted" />
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                class="input ps-10!"
                placeholder="جستجوی پروژه یا مشتری…"
                aria-label="جستجو"
            />
        </div>

        <x-ui.select wire:model.live="status" :options="$statusOptions" label="فیلتر وضعیت" class="w-44" />
        <x-ui.select wire:model.live="sort" :options="$sortOptions" label="مرتب‌سازی" class="w-44" />

        @if ($hasFilters)
            <button
                type="button"
                wire:click="$set('search', ''); $set('status', '')"
                class="text-sm text-muted transition hover:text-primary"
            >پاک کردن فیلترها</button>
        @endif

        {{-- نمای کارتی / ردیفی --}}
        <div class="ms-auto inline-flex rounded-control border border-line bg-surface p-1" role="group" aria-label="نوع نمایش">
            @foreach (['grid' => ['grid', 'کارتی'], 'list' => ['list', 'ردیفی']] as $key => [$icon, $text])
                <button
                    type="button"
                    wire:click="$set('layout', '{{ $key }}')"
                    aria-pressed="{{ $layout === $key ? 'true' : 'false' }}"
                    title="نمای {{ $text }}"
                    @class([
                        'flex size-8 items-center justify-center rounded-md transition',
                        'bg-primary-soft text-primary' => $layout === $key,
                        'text-muted hover:text-fg' => $layout !== $key,
                    ])
                >
                    <x-ui.icon :name="$icon" class="size-4.5" />
                </button>
            @endforeach
        </div>
    </div>

    @if ($projects->isEmpty())
        <div class="card">
            @if ($hasFilters)
                <x-ui.empty-state title="پروژه‌ای پیدا نشد" icon="search">
                    فیلترها یا عبارت جستجو را تغییر بده.
                </x-ui.empty-state>
            @elseif ($archivedView)
                <x-ui.empty-state title="پروژه‌ی آرشیوی نداری" icon="archive">
                    پروژه‌هایی که آرشیو کنی اینجا نگه داشته می‌شوند.
                </x-ui.empty-state>
            @else
                <x-ui.empty-state title="هنوز پروژه‌ای نیست" icon="folder">
                    @can('create', \App\Models\Project::class)
                        اولین پروژه را بساز تا تسک‌ها را در آن مدیریت کنی.
                    @else
                        هنوز عضو هیچ پروژه‌ای نیستی. از مدیر بخواه تو را اضافه کند.
                    @endcan
                </x-ui.empty-state>
            @endif
        </div>
    @else
        @if ($grid)
            {{-- نمای کارتی --}}
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($projects as $p)
                    @php
                        $days = \App\Support\Dates::daysFromToday($p->end_date);
                        $live = $p->status !== \App\Enums\ProjectStatus::Done && ! $archivedView;
                        $open = $p->tasks_count - $p->done_tasks_count;
                    @endphp

                    <article wire:key="project-{{ $p->id }}" class="card relative flex flex-col p-5 transition hover:border-primary/40">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg text-base font-bold text-white"
                                style="background-color: {{ $p->color ?? '#5B5BD6' }}"
                            >{{ mb_substr($p->name, 0, 1) }}</span>

                            <div class="min-w-0 flex-1">
                                <a href="{{ route('projects.show', $p) }}" wire:navigate class="block truncate font-bold after:absolute after:inset-0">{{ $p->name }}</a>
                                <p class="mt-0.5 truncate text-sm text-muted">{{ $p->client?->name ?? 'بدون مشتری' }}</p>
                            </div>

                            @include('pages.projects.partials.menu')
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium" style="color: {{ $p->status->color() }}">
                                <span class="size-1.5 rounded-full" style="background-color: {{ $p->status->color() }}"></span>
                                {{ $p->status->label() }}
                            </span>
                            <span class="tabular text-xs text-muted">{{ $p->progress }}%</span>
                        </div>
                        <x-ui.progress :value="$p->progress" :tone="$p->progress >= 100 ? 'success' : 'primary'" class="mt-2" />

                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                            <span><span class="tabular font-medium text-fg">{{ $open }}</span> تسک باز از <span class="tabular">{{ $p->tasks_count }}</span></span>
                            @if ($p->overdue_count > 0)
                                <span class="tabular rounded-full bg-danger/10 px-2 py-0.5 font-medium text-danger">{{ $p->overdue_count }} عقب‌افتاده</span>
                            @endif
                        </div>

                        <div class="mt-auto flex items-center justify-between gap-3 border-t border-line pt-4" style="margin-top: 1.25rem">
                            <div class="flex -space-x-2 rtl:space-x-reverse">
                                @foreach ($p->members->take(4) as $m)
                                    <x-ui.avatar :user="$m" :size="28" class="ring-2 ring-surface" :title="$m->name" />
                                @endforeach
                                @if ($p->members->count() > 4)
                                    <span class="tabular flex size-7 items-center justify-center rounded-full bg-line text-[0.6875rem] font-medium text-muted ring-2 ring-surface">+{{ $p->members->count() - 4 }}</span>
                                @endif
                            </div>

                            @if ($p->end_date)
                                <span @class([
                                    'inline-flex items-center gap-1 text-xs',
                                    'font-medium text-danger' => $live && $days < 0,
                                    'text-muted' => ! ($live && $days < 0),
                                ]) title="{{ \App\Support\Dates::short($p->end_date) }}">
                                    <x-ui.icon name="calendar" class="size-3.5" />
                                    @if ($live)
                                        {{ $days < 0 ? abs($days).' روز دیرکرد' : ($days === 0 ? 'امروز تمام می‌شود' : $days.' روز مانده') }}
                                    @else
                                        {{ \App\Support\Dates::short($p->end_date) }}
                                    @endif
                                </span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($projects->hasPages())
                <div class="card"><x-ui.pagination :paginator="$projects" /></div>
            @endif
        @else
            {{-- نمای ردیفی --}}
            <div class="card">
                <div class="hidden gap-4 border-b border-line px-5 py-3 text-xs font-medium text-muted md:grid md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.3fr)_minmax(0,1fr)_3rem] lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)_3rem]">
                    <div>پروژه</div>
                    <div>وضعیت</div>
                    <div>پیشرفت</div>
                    <div>اعضا</div>
                    <div class="hidden lg:block">پایان</div>
                    <div></div>
                </div>

                <ul class="divide-y divide-line">
                    @foreach ($projects as $p)
                        @php
                            $days = \App\Support\Dates::daysFromToday($p->end_date);
                            $live = $p->status !== \App\Enums\ProjectStatus::Done && ! $archivedView;
                        @endphp

                        <li
                            wire:key="project-{{ $p->id }}"
                            class="relative flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4 transition hover:bg-line/30 md:grid md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.3fr)_minmax(0,1fr)_3rem] lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)_3rem]"
                        >
                            <div class="flex min-w-0 items-center gap-3 max-md:flex-1 max-md:basis-52">
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold text-white"
                                    style="background-color: {{ $p->color ?? '#5B5BD6' }}"
                                >{{ mb_substr($p->name, 0, 1) }}</span>
                                <div class="min-w-0">
                                    <a href="{{ route('projects.show', $p) }}" wire:navigate class="block truncate font-medium after:absolute after:inset-0">{{ $p->name }}</a>
                                    <p class="truncate text-xs text-muted">{{ $p->client?->name ?? 'بدون مشتری' }}</p>
                                </div>
                            </div>

                            <div>
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium" style="color: {{ $p->status->color() }}">
                                    <span class="size-1.5 rounded-full" style="background-color: {{ $p->status->color() }}"></span>
                                    {{ $p->status->label() }}
                                </span>
                            </div>

                            <div class="flex items-center gap-3 max-md:w-40">
                                <x-ui.progress :value="$p->progress" :tone="$p->progress >= 100 ? 'success' : 'primary'" class="flex-1" />
                                <span class="tabular w-9 shrink-0 text-xs text-muted">{{ $p->progress }}%</span>
                            </div>

                            <div class="flex -space-x-2 rtl:space-x-reverse">
                                @foreach ($p->members->take(3) as $m)
                                    <x-ui.avatar :user="$m" :size="26" class="ring-2 ring-surface" :title="$m->name" />
                                @endforeach
                                @if ($p->members->count() > 3)
                                    <span class="tabular flex size-[1.625rem] items-center justify-center rounded-full bg-line text-[0.625rem] font-medium text-muted ring-2 ring-surface">+{{ $p->members->count() - 3 }}</span>
                                @endif
                            </div>

                            <div class="hidden text-xs lg:block {{ $live && $days !== null && $days < 0 ? 'font-medium text-danger' : 'text-muted' }}">
                                {{ \App\Support\Dates::short($p->end_date) }}
                            </div>

                            <div class="ms-auto md:ms-0">
                                @include('pages.projects.partials.menu')
                            </div>
                        </li>
                    @endforeach
                </ul>

                <x-ui.pagination :paginator="$projects" />
            </div>
        @endif
    @endif
</div>
