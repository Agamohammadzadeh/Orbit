<?php

use App\Actions\Tasks\MoveTask;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Queries\ProjectTaskFilter;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    private const DONE_LIMIT = 25;

    public Project $project;

    #[Url(as: 'q')]
    public string $search = '';

    /** '' | me | none | شناسه‌ی کاربر */
    #[Url]
    public string $assignee = '';

    /** '' | active | none | شناسه‌ی اسپرینت */
    #[Url]
    public string $sprint = '';

    #[Url]
    public string $phase = '';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    /* ---------------- داده‌ها ---------------- */

    private function filtered()
    {
        return ProjectTaskFilter::apply(
            Task::query(), $this->project, auth()->id(), $this->search, $this->assignee, $this->sprint, $this->phase
        );
    }

    /** ستون «تمام‌شده» فقط ۲۵ مورد آخر را نشان می‌دهد؛ بقیه به ترتیب position */
    #[Computed]
    public function columns(): array
    {
        $columns = [];

        foreach (TaskStatus::cases() as $status) {
            $query = $this->filtered()
                ->inStatus($status)
                ->with(['assignee:id,name,avatar_path', 'labels:id,name,color'])
                ->withCardCounts();

            $status === TaskStatus::Done
                ? $query->latest('completed_at')->limit(self::DONE_LIMIT)
                : $query->orderBy('position')->orderBy('id');

            $tasks = $query->get();

            $columns[$status->value] = [
                'status' => $status,
                'tasks' => $status === TaskStatus::Done ? $tasks->sortBy('position')->values() : $tasks,
                'total' => $this->filtered()->inStatus($status)->count(),
            ];
        }

        return $columns;
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

    #[On('task-saved')]
    public function refresh(): void
    {
        unset($this->columns);
    }

    /* ---------------- جابه‌جایی ---------------- */

    /** شناسه‌های تسک‌های قابل‌دیدن یک ستون (بدون تسک در حال جابه‌جایی) */
    private function visibleIds(TaskStatus $status, int $exceptId)
    {
        $query = $this->filtered()->inStatus($status)->select(['id', 'position', 'completed_at']);

        $rows = $status === TaskStatus::Done
            ? $query->latest('completed_at')->limit(self::DONE_LIMIT)->get()->sortBy('position')
            : $query->orderBy('position')->orderBy('id')->get();

        return $rows->pluck('id')->reject(fn ($id) => $id === $exceptId)->values();
    }

    /** handler ‏wire:sort: (شناسه‌ی تسک، جایگاه جدید از صفر، شناسه‌ی گروه = وضعیت ستون مقصد) */
    public function sortTask(int $id, int $position, string $status): void
    {
        $target = TaskStatus::tryFrom($status);
        abort_unless($target, 422);
        abort_if($this->project->isArchived(), 403);

        $task = Task::query()->where('project_id', $this->project->id)->findOrFail($id);
        Gate::authorize('update', $task);

        $ids = $this->visibleIds($target, $task->id);
        $prevId = $position > 0 ? ($ids[$position - 1] ?? $ids->last()) : null;
        $nextId = $ids[$position] ?? null;

        (new MoveTask)->handle(auth()->user(), $task, $target, $prevId, $nextId);

        unset($this->columns);
        $this->dispatch('task-updated');
    }
};
?>

@php
    $columns = $this->columns;
    $me = auth()->user();
    $canWrite = ! $project->isArchived() && $me->can('create', [\App\Models\Task::class, $project]);
    $hasFilters = $search !== '' || $assignee !== '' || $sprint !== '' || $phase !== '';
    $phaseOptions = ['' => 'همه‌ی مراحل', 'none' => 'بدون مرحله'] + $this->phases;

    $assigneeOptions = ['' => 'همه‌ی مسئول‌ها', 'me' => 'فقط من', 'none' => 'بدون مسئول'] + $this->members;
    $sprintOptions = ['' => 'همه‌ی اسپرینت‌ها', 'active' => 'اسپرینت فعال', 'none' => 'بدون اسپرینت'] + $this->sprints;
@endphp

<div class="space-y-4">
    {{-- فیلترها --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative min-w-56 flex-1 sm:max-w-xs">
            <x-ui.icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4.5 -translate-y-1/2 text-muted" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input ps-10!" placeholder="جستجوی تسک…" aria-label="جستجوی تسک" />
        </div>

        <x-ui.select wire:model.live="assignee" :options="$assigneeOptions" label="فیلتر مسئول" class="w-48" />
        <x-ui.select wire:model.live="sprint" :options="$sprintOptions" label="فیلتر اسپرینت" class="w-48" />
        <x-ui.select wire:model.live="phase" :options="$phaseOptions" label="فیلتر مرحله" class="w-48" />

        @if ($hasFilters)
            <button type="button" wire:click="$set('search', ''); $set('assignee', ''); $set('sprint', ''); $set('phase', '')" class="text-sm text-muted transition hover:text-primary">
                پاک کردن فیلترها
            </button>
        @endif
    </div>

    {{-- ستون‌ها --}}
    <div class="overflow-x-auto pb-4">
        <div class="flex min-w-max items-start gap-4">
            @foreach ($columns as $key => $col)
                @php
                    $status = $col['status'];
                    $shown = $col['tasks']->count();
                @endphp

                <section wire:key="column-{{ $key }}" class="flex w-80 shrink-0 flex-col rounded-card border border-line bg-line/30" aria-label="{{ $status->label() }}">
                    <header class="flex items-center gap-2.5 px-3.5 pt-3.5">
                        <x-task.status-icon :status="$status" class="size-4.5" />
                        <h3 class="text-sm font-bold">{{ $status->label() }}</h3>
                        <span class="tabular rounded-full bg-line px-2 py-0.5 text-xs text-muted">{{ $col['total'] }}</span>

                        @if ($canWrite)
                            <button
                                type="button"
                                wire:click="$dispatch('open-task-form', { status: '{{ $key }}', projectId: {{ $project->id }} })"
                                class="ms-auto flex size-7 items-center justify-center rounded-md text-muted transition hover:bg-line hover:text-fg"
                                aria-label="تسک جدید در {{ $status->label() }}"
                            >
                                <x-ui.icon name="plus" class="size-4.5" />
                            </button>
                        @endif
                    </header>

                    <div
                        @if ($canWrite)
                            wire:sort="sortTask"
                        wire:sort:group="board"
                        wire:sort:group-id="{{ $key }}"
                        @endif
                        class="min-h-24 space-y-2.5 p-2.5"
                    >
                        @forelse ($col['tasks'] as $task)
                            @php
                                $hot = in_array($task->priority, [\App\Enums\TaskPriority::High, \App\Enums\TaskPriority::Urgent], true);
                                $done = $task->status->isDone();
                            @endphp

                            <article
                                wire:key="task-{{ $task->id }}"
                                @if ($canWrite)
                                    wire:sort:item="{{ $task->id }}"
                                wire:click="$dispatch('edit-task', { id: {{ $task->id }} })"
                                @endif
                                @class([
                                    'rounded-control border border-line bg-surface p-3 shadow-card transition hover:border-primary/40',
                                    'cursor-grab active:cursor-grabbing' => $canWrite,
                                    'opacity-75' => $done,
                                ])
                            >
                                @if ($task->labels->isNotEmpty())
                                    <div class="mb-2 flex flex-wrap gap-1.5">
                                        @foreach ($task->labels->take(3) as $l)
                                            <span class="rounded-full px-2 py-0.5 text-[0.6875rem] font-medium" style="background-color: {{ $l->color }}22; color: {{ $l->color }}">{{ $l->name }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <a
                                    href="{{ route('tasks.show', $task) }}"
                                    wire:navigate
                                    @click.stop
                                    @class([
                                        'line-clamp-2 text-sm font-medium leading-relaxed transition hover:text-primary',
                                        'text-muted line-through' => $done,
                                    ])
                                >{{ $task->title }}</a>

                                <div class="mt-2.5 flex flex-wrap items-center gap-x-2.5 gap-y-1.5 text-xs text-muted">
                                    <span style="color: {{ $task->type->color() }}" class="font-medium">{{ $task->type->label() }}</span>

                                    @if ($hot && ! $done)
                                        <span style="color: {{ $task->priority->color() }}" title="اولویت {{ $task->priority->label() }}">
                                            <x-ui.icon name="flag" class="size-3.5" />
                                        </span>
                                    @endif

                                    <x-task.due :task="$task" />

                                    @if ($task->checklist_items_count > 0)
                                        <span class="tabular inline-flex items-center gap-1" title="چک‌لیست">
                                            <x-ui.icon name="check-square" class="size-3.5" />
                                            {{ $task->checklist_done_count }}/{{ $task->checklist_items_count }}
                                        </span>
                                    @endif
                                    @if ($task->comments_count > 0)
                                        <span class="tabular inline-flex items-center gap-1" title="کامنت‌ها">
                                            <x-ui.icon name="message" class="size-3.5" />
                                            {{ $task->comments_count }}
                                        </span>
                                    @endif
                                    @if ($task->attachments_count > 0)
                                        <span class="tabular inline-flex items-center gap-1" title="پیوست‌ها">
                                            <x-ui.icon name="paperclip" class="size-3.5" />
                                            {{ $task->attachments_count }}
                                        </span>
                                    @endif

                                    <span class="ms-auto">
                                        @if ($task->assignee)
                                            <x-ui.avatar :user="$task->assignee" :size="24" :title="$task->assignee->name" />
                                        @else
                                            <span class="flex size-6 items-center justify-center rounded-full border border-dashed border-line text-muted" title="بدون مسئول">
                                                <x-ui.icon name="user" class="size-3.5" />
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            </article>
                        @empty
                            <p class="pointer-events-none px-2 py-6 text-center text-xs text-muted">
                                {{ $hasFilters ? 'موردی با این فیلتر نیست' : 'تسکی نیست' }}
                            </p>
                        @endforelse
                    </div>

                    @if ($status->isDone() && $col['total'] > $shown)
                        <p class="tabular border-t border-line px-3.5 py-2.5 text-center text-xs text-muted">
                            {{ $shown }} مورد اخیر از {{ $col['total'] }} (بقیه در تب «لیست»)
                        </p>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</div>
