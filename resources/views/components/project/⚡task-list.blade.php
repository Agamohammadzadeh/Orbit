<?php

use App\Actions\Tasks\ChangeTaskStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Queries\ProjectTaskFilter;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Project $project;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $assignee = '';

    #[Url]
    public string $sprint = '';

    #[Url]
    public string $phase = '';

    /** '' | open | todo | in_progress | in_review | done */
    #[Url]
    public string $status = '';

    /** updated | due | priority */
    #[Url]
    public string $sort = 'updated';

    public function mount(Project $project): void
    {
        $this->project = $project;

        if (! in_array($this->sort, ['updated', 'due', 'priority'], true)) {
            $this->sort = 'updated';
        }
    }

    #[Computed]
    public function tasks()
    {
        $query = ProjectTaskFilter::apply(
            Task::query(), $this->project, auth()->id(), $this->search, $this->assignee, $this->sprint, $this->phase
        )
            ->with(['assignee:id,name,avatar_path', 'labels:id,name,color', 'sprint:id,name'])
            ->when($this->status === 'open', fn ($q) => $q->open())
            ->when(TaskStatus::tryFrom($this->status), fn ($q, $s) => $q->inStatus($s));

        match ($this->sort) {
            'due' => $query->orderByRaw('due_date IS NULL')->orderBy('due_date'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")->latest('updated_at'),
            default => $query->latest('updated_at'),
        };

        return $query->paginate(20);
    }

    #[Computed]
    public function members(): array
    {
        return $this->project->members()->orderBy('name')->get(['users.id', 'users.name'])->pluck('name', 'id')->all();
    }

    #[Computed]
    public function sprints(): array
    {
        return $this->project->sprints()->get(['id', 'name'])->pluck('name', 'id')->all();
    }

    #[Computed]
    public function phases(): array
    {
        return $this->project->phases()->get(['id', 'name'])->pluck('name', 'id')->all();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'assignee', 'sprint', 'status', 'sort', 'phase'], true)) {
            $this->resetPage();
        }
    }

    #[On('task-saved')]
    public function refresh(): void
    {
        unset($this->tasks);
    }

    public function setStatus(int $id, string $status): void
    {
        $task = Task::query()->where('project_id', $this->project->id)->findOrFail($id);
        Gate::authorize('update', $task);
        abort_if($this->project->isArchived(), 403);

        $to = TaskStatus::tryFrom($status);
        abort_unless($to, 422);

        (new ChangeTaskStatus)->handle(auth()->user(), $task, $to);

        unset($this->tasks);
        $this->dispatch('task-updated');
    }
};
?>

@php
    $tasks = $this->tasks;
    $canWrite = ! $project->isArchived() && auth()->user()->can('create', [\App\Models\Task::class, $project]);
    $hasFilters = $search !== '' || $assignee !== '' || $sprint !== '' || $status !== '' || $phase !== '';
    $phaseOptions = ['' => 'همه‌ی مراحل', 'none' => 'بدون مرحله'] + $this->phases;

    $statusOptions = ['' => 'همه‌ی وضعیت‌ها', 'open' => 'فقط باز'];
    foreach (\App\Enums\TaskStatus::cases() as $s) {
        $statusOptions[$s->value] = $s->label();
    }
    $assigneeOptions = ['' => 'همه‌ی مسئول‌ها', 'me' => 'فقط من', 'none' => 'بدون مسئول'] + $this->members;
    $sprintOptions = ['' => 'همه‌ی اسپرینت‌ها', 'active' => 'اسپرینت فعال', 'none' => 'بدون اسپرینت'] + $this->sprints;
    $sortOptions = ['updated' => 'آخرین تغییر', 'due' => 'نزدیک‌ترین ددلاین', 'priority' => 'اولویت'];

    $cols = 'md:grid-cols-[minmax(0,3fr)_minmax(0,1.2fr)_minmax(0,0.8fr)_minmax(0,1fr)_2.5rem]';
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative min-w-56 flex-1 sm:max-w-xs">
            <x-ui.icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4.5 -translate-y-1/2 text-muted" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input ps-10!" placeholder="جستجوی تسک…" aria-label="جستجوی تسک" />
        </div>

        <x-ui.select wire:model.live="status" :options="$statusOptions" label="فیلتر وضعیت" class="w-44" />
        <x-ui.select wire:model.live="assignee" :options="$assigneeOptions" label="فیلتر مسئول" class="w-44" />
        <x-ui.select wire:model.live="sprint" :options="$sprintOptions" label="فیلتر اسپرینت" class="w-44" />
        <x-ui.select wire:model.live="phase" :options="$phaseOptions" label="فیلتر مرحله" class="w-44" />
        <x-ui.select wire:model.live="sort" :options="$sortOptions" label="مرتب‌سازی" class="w-48" />

        @if ($hasFilters)
            <button type="button" wire:click="$set('search', ''); $set('assignee', ''); $set('sprint', ''); $set('status', ''); $set('phase', '')" class="text-sm text-muted transition hover:text-primary">
                پاک کردن فیلترها
            </button>
        @endif
    </div>

    <div class="card">
        <div class="hidden gap-4 border-b border-line px-5 py-3 text-xs font-medium text-muted md:grid {{ $cols }}">
            <div>تسک</div>
            <div>مسئول</div>
            <div>اولویت</div>
            <div>ددلاین</div>
            <div></div>
        </div>

        <div class="divide-y divide-line">
            @forelse ($tasks as $task)
                @php
                    $done = $task->status->isDone();
                    $hot = in_array($task->priority, [\App\Enums\TaskPriority::High, \App\Enums\TaskPriority::Urgent], true);
                @endphp

                <div wire:key="row-{{ $task->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3 transition hover:bg-line/30 md:grid {{ $cols }}">
                    {{-- وضعیت و عنوان --}}
                    <div class="flex min-w-0 items-center gap-3 max-md:basis-full">
                        @if ($canWrite)
                            <x-ui.dropdown align="start" width="w-52" class="shrink-0">
                                <x-slot:trigger>
                                    <button type="button" @click="toggle()" :aria-expanded="open" aria-haspopup="menu" class="flex size-8 items-center justify-center rounded-full transition hover:bg-line/60" title="تغییر وضعیت: {{ $task->status->label() }}">
                                        <x-task.status-icon :status="$task->status" />
                                    </button>
                                </x-slot:trigger>

                                @foreach (\App\Enums\TaskStatus::cases() as $s)
                                    <x-ui.dropdown-item wire:click="setStatus({{ $task->id }}, '{{ $s->value }}')">
                                        <x-task.status-icon :status="$s" class="size-4.5" />
                                        {{ $s->label() }}
                                        @if ($s === $task->status)
                                            <x-ui.icon name="check" class="ms-auto size-4 text-primary" />
                                        @endif
                                    </x-ui.dropdown-item>
                                @endforeach
                            </x-ui.dropdown>
                        @else
                            <span class="flex size-8 shrink-0 items-center justify-center"><x-task.status-icon :status="$task->status" /></span>
                        @endif

                        <div class="min-w-0">
                            <a
                                href="{{ route('tasks.show', $task) }}"
                                wire:navigate
                                @class(['block truncate text-sm font-medium transition hover:text-primary', 'text-muted line-through' => $done])
                            >
                                {{ $task->title }}
                            </a>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                                <span style="color: {{ $task->type->color() }}" class="font-medium">{{ $task->type->label() }}</span>
                                @if ($task->sprint)
                                    <span class="inline-flex items-center gap-1"><x-ui.icon name="zap" class="size-3" />{{ $task->sprint->name }}</span>
                                @endif
                                @foreach ($task->labels->take(2) as $l)
                                    <span class="rounded-full px-2 py-0.5 text-[0.6875rem] font-medium" style="background-color: {{ $l->color }}22; color: {{ $l->color }}">{{ $l->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- مسئول --}}
                    <div class="flex min-w-0 items-center gap-2">
                        @if ($task->assignee)
                            <x-ui.avatar :user="$task->assignee" :size="24" />
                            <span class="truncate text-sm">{{ $task->assignee->name }}</span>
                        @else
                            <span class="text-sm text-muted">—</span>
                        @endif
                    </div>

                    {{-- اولویت --}}
                    <div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium" style="color: {{ $task->priority->color() }}">
                            @if ($hot) <x-ui.icon name="flag" class="size-3.5" /> @endif
                            {{ $task->priority->label() }}
                        </span>
                    </div>

                    {{-- ددلاین --}}
                    <div>
                        @if ($task->due_date)
                            <x-task.due :task="$task" />
                        @else
                            <span class="text-sm text-muted">—</span>
                        @endif
                    </div>

                    {{-- ویرایش --}}
                    <div class="ms-auto md:ms-0">
                        @if ($canWrite)
                            <button type="button" wire:click="$dispatch('edit-task', { id: {{ $task->id }} })" class="flex size-9 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg" title="ویرایش" aria-label="ویرایش {{ $task->title }}">
                                <x-ui.icon name="edit" class="size-4.5" />
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <x-ui.empty-state :title="$hasFilters ? 'تسکی پیدا نشد' : 'هنوز تسکی نیست'" icon="list">
                    {{ $hasFilters ? 'فیلترها یا عبارت جستجو را تغییر بده.' : ($canWrite ? 'با دکمه‌ی «تسک جدید» اولین تسک را بساز.' : '') }}
                </x-ui.empty-state>
            @endforelse
        </div>

        <x-ui.pagination :paginator="$tasks" />
    </div>
</div>
