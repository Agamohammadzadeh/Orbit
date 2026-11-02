<?php

use App\Actions\Tasks\ChangeTaskStatus;
use App\Enums\TaskStatus;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('تسک')] class extends Component {
    public Task $task;

    /** comments | time | activity */
    #[Url]
    public string $tab = 'comments';

    public function mount(Task $task): void
    {
        Gate::authorize('view', $task);

        $this->task = $task;

        if (! in_array($this->tab, ['comments', 'time', 'activity'], true)) {
            $this->tab = 'comments';
        }
    }

    /* ---------------- داده‌ها ---------------- */

    #[Computed]
    public function project(): Project
    {
        return Project::query()->findOrFail($this->task->project_id);
    }

    #[Computed]
    public function assignee(): ?User
    {
        return $this->task->assignee_id ? User::query()->find($this->task->assignee_id) : null;
    }

    #[Computed]
    public function creator(): ?User
    {
        return $this->task->creator_id ? User::query()->find($this->task->creator_id) : null;
    }

    #[Computed]
    public function phase(): ?Phase
    {
        return $this->task->phase_id ? Phase::query()->find($this->task->phase_id) : null;
    }

    #[Computed]
    public function sprint(): ?Sprint
    {
        return $this->task->sprint_id ? Sprint::query()->find($this->task->sprint_id) : null;
    }

    #[Computed]
    public function labels()
    {
        return $this->task->labels()->get(['labels.id', 'labels.name', 'labels.color']);
    }

    #[Computed]
    public function spent(): int
    {
        return (int) $this->task->timeEntries()->sum('minutes');
    }

    private function forget(): void
    {
        unset($this->project, $this->assignee, $this->creator, $this->phase, $this->sprint, $this->labels, $this->spent);
    }

    /** بعد از ویرایش از مودال سراسری (یا حذف تسک) صفحه به‌روز می‌شود */
    #[On('task-saved')]
    public function refresh(): void
    {
        $fresh = Task::query()->find($this->task->id);

        if (! $fresh) {
            $this->redirectRoute('projects.show', ['project' => $this->project], navigate: true);

            return;
        }

        $this->task = $fresh;
        $this->forget();
    }

    #[On('time-logged')]
    public function refreshSpent(): void
    {
        unset($this->spent);
    }

    public function setStatus(string $status): void
    {
        Gate::authorize('update', $this->task);
        abort_if($this->project->isArchived(), 403);

        $to = TaskStatus::tryFrom($status);
        abort_unless($to, 422);

        (new ChangeTaskStatus)->handle(auth()->user(), $this->task, $to);

        $this->task = Task::query()->findOrFail($this->task->id);
        $this->dispatch('task-updated');
    }
};
?>

@php
    $task = $this->task;
    $project = $this->project;
    $me = auth()->user();
    $archived = $project->isArchived();
    $canWrite = ! $archived && $me->can('update', $task);

    $minutes = function (int $m): string {
        if ($m <= 0) {
            return '0';
        }

        return collect([intdiv($m, 60) ? intdiv($m, 60).' ساعت' : null, $m % 60 ? ($m % 60).' دقیقه' : null])
            ->filter()->implode(' و ');
    };

    $estimate = $task->estimate_minutes;
    $spent = $this->spent;
    $over = $estimate && $spent > $estimate;
    $spentPct = $estimate ? min(100, (int) round($spent / $estimate * 100)) : 0;

    $tabs = ['comments' => 'کامنت‌ها', 'time' => 'زمان', 'activity' => 'تاریخچه'];
    $done = $task->status->isDone();
@endphp

<div class="space-y-6">
    {{-- مسیر و عنوان --}}
    <div class="space-y-3">
        <nav class="flex flex-wrap items-center gap-1.5 text-sm text-muted" aria-label="مسیر">
            <a href="{{ route('projects.index') }}" wire:navigate class="transition hover:text-primary">پروژه‌ها</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('projects.show', $project) }}" wire:navigate class="inline-flex items-center gap-1.5 transition hover:text-primary">
                <span class="size-2 rounded-[3px]" style="background-color: {{ $project->color ?? '#5B5BD6' }}"></span>
                {{ $project->name }}
            </a>
            <span aria-hidden="true">/</span>
            <span class="tabular">#{{ $task->id }}</span>
        </nav>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1 basis-80">
                <h1 @class(['text-2xl font-bold leading-relaxed tracking-tight', 'text-muted line-through' => $done])>{{ $task->title }}</h1>

                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                    <span class="font-medium" style="color: {{ $task->type->color() }}">{{ $task->type->label() }}</span>
                    <span class="inline-flex items-center gap-1.5 font-medium" style="color: {{ $task->priority->color() }}">
                        <x-ui.icon name="flag" class="size-3.5" />
                        اولویت {{ $task->priority->label() }}
                    </span>
                    @foreach ($this->labels as $l)
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" style="background-color: {{ $l->color }}22; color: {{ $l->color }}">{{ $l->name }}</span>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                {{-- وضعیت (تغییر سریع) --}}
                @if ($canWrite)
                    <x-ui.dropdown width="w-52">
                        <x-slot:trigger>
                            <button type="button" @click="toggle()" :aria-expanded="open" aria-haspopup="menu" class="btn-secondary">
                                <x-task.status-icon :status="$task->status" class="size-4.5" />
                                {{ $task->status->label() }}
                                <x-ui.icon name="chevron-down" class="size-4 text-muted" />
                            </button>
                        </x-slot:trigger>

                        @foreach (\App\Enums\TaskStatus::cases() as $s)
                            <x-ui.dropdown-item wire:click="setStatus('{{ $s->value }}')">
                                <x-task.status-icon :status="$s" class="size-4.5" />
                                {{ $s->label() }}
                                @if ($s === $task->status)
                                    <x-ui.icon name="check" class="ms-auto size-4 text-primary" />
                                @endif
                            </x-ui.dropdown-item>
                        @endforeach
                    </x-ui.dropdown>

                    <button type="button" wire:click="$dispatch('edit-task', { id: {{ $task->id }} })" class="btn-primary">
                        <x-ui.icon name="edit" class="size-4.5" />
                        ویرایش
                    </button>
                @else
                    <span class="inline-flex h-11 items-center gap-2 rounded-control border border-line bg-surface px-4 text-sm">
                        <x-task.status-icon :status="$task->status" class="size-4.5" />
                        {{ $task->status->label() }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    @if ($archived)
        <div class="flex items-center gap-2.5 rounded-control border border-line bg-surface px-4 py-3 text-sm text-muted">
            <x-ui.icon name="archive" class="size-4.5" />
            پروژه‌ی این تسک آرشیو شده و تسک فقط‌خواندنی است.
        </div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-3">
        {{-- ستون اصلی --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="card p-6">
                <h2 class="text-base font-bold">توضیحات</h2>
                @if ($task->description)
                    <div class="rich mt-3 text-sm leading-loose">{!! \App\Support\RichText::render($task->description) !!}</div>
                @else
                    <p class="mt-3 text-sm text-muted">
                        توضیحاتی ثبت نشده.
                        @if ($canWrite)
                            <button type="button" wire:click="$dispatch('edit-task', { id: {{ $task->id }} })" class="text-primary hover:underline">افزودن توضیحات</button>
                        @endif
                    </p>
                @endif
            </div>

            <livewire:task.checklist :task="$task" />
            <livewire:task.attachments :task="$task" />
            <livewire:task.links :task="$task" />

            {{-- کامنت‌ها، زمان، تاریخچه --}}
            <div class="card">
                <div class="flex gap-1 overflow-x-auto border-b border-line px-3" role="tablist">
                    @foreach ($tabs as $key => $text)
                        <button
                            type="button"
                            role="tab"
                            wire:click="$set('tab', '{{ $key }}')"
                            aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                            @class([
                                '-mb-px h-12 shrink-0 border-b-2 px-4 text-sm font-medium transition',
                                'border-primary text-primary' => $tab === $key,
                                'border-transparent text-muted hover:text-fg' => $tab !== $key,
                            ])
                        >{{ $text }}</button>
                    @endforeach
                </div>

                @if ($tab === 'time')
                    <livewire:task.time :task="$task" />
                @elseif ($tab === 'activity')
                    <livewire:task.activity :task="$task" />
                @else
                    <livewire:task.comments :task="$task" />
                @endif
            </div>
        </div>

        {{-- سایدبار جزئیات --}}
        <aside class="card p-6 lg:sticky lg:top-24">
            <h2 class="text-base font-bold">جزئیات</h2>

            <dl class="mt-4 space-y-4 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-muted">مسئول</dt>
                    <dd class="flex min-w-0 items-center gap-2 font-medium">
                        @if ($this->assignee)
                            <x-ui.avatar :user="$this->assignee" :size="24" />
                            <span class="truncate">{{ $this->assignee->name }}</span>
                        @else
                            <span class="text-muted">بدون مسئول</span>
                        @endif
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <dt class="text-muted">ددلاین</dt>
                    <dd>
                        @if ($task->due_date)
                            <x-task.due :task="$task" />
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <dt class="text-muted">مرحله</dt>
                    <dd class="truncate font-medium">{{ $this->phase?->name ?? '—' }}</dd>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <dt class="text-muted">اسپرینت</dt>
                    <dd class="truncate font-medium">{{ $this->sprint?->name ?? '—' }}</dd>
                </div>

                <div class="border-t border-line pt-4">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">زمان ثبت‌شده</dt>
                        <dd @class(['font-medium', 'text-danger' => $over])>{{ $minutes($spent) }}</dd>
                    </div>
                    @if ($estimate)
                        <x-ui.progress :value="$spentPct" :tone="$over ? 'danger' : 'primary'" class="mt-2.5" />
                        <p class="mt-1.5 text-xs text-muted">برآورد: {{ $minutes($estimate) }}</p>
                    @else
                        <p class="mt-1.5 text-xs text-muted">برآوردی تعیین نشده.</p>
                    @endif
                </div>

                <div class="space-y-3 border-t border-line pt-4">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">ساخته‌شده توسط</dt>
                        <dd class="truncate font-medium">{{ $this->creator?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">تاریخ ساخت</dt>
                        <dd class="tabular" title="{{ $task->created_at->diffForHumans() }}">{{ \App\Support\Dates::short($task->created_at) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">آخرین تغییر</dt>
                        <dd>{{ $task->updated_at->diffForHumans() }}</dd>
                    </div>
                    @if ($task->completed_at)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted">تکمیل</dt>
                            <dd class="text-success">{{ $task->completed_at->diffForHumans() }}</dd>
                        </div>
                    @endif
                </div>
            </dl>
        </aside>
    </div>
</div>
