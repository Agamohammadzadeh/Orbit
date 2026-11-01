<?php

use App\Actions\Tasks\ChangeTaskStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Queries\DashboardQuery;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

new class extends Component {
    /** از صفحه‌ی داشبورد می‌آید و با تغییرش لیست دوباره ساخته می‌شود */
    #[Reactive]
    public string $filter = '';

    /** @var array<string,bool> گروه‌هایی که «نمایش همه» زده شده */
    public array $expanded = [];

    #[Computed]
    public function groups(): array
    {
        $tasks = (new DashboardQuery)->myTasks(auth()->user(), $this->filter);

        if ($this->filter === 'done') {
            return $tasks->isEmpty() ? [] : [[
                'key' => 'done',
                'label' => 'انجام‌شده این هفته',
                'tone' => 'text-success',
                'tasks' => $tasks,
            ]];
        }

        $today = today();
        $weekEnd = DashboardQuery::weekEnd();

        $buckets = $tasks->groupBy(fn (Task $t) => match (true) {
            $t->due_date === null => 'none',
            $t->due_date->lt($today) => 'overdue',
            $t->due_date->isSameDay($today) => 'today',
            $t->due_date->lte($weekEnd) => 'week',
            default => 'later',
        });

        $defs = [
            'overdue' => ['عقب‌افتاده', 'text-danger'],
            'today' => ['امروز', 'text-primary'],
            'week' => ['این هفته', 'text-fg'],
            'later' => ['بعداً', 'text-muted'],
            'none' => ['بدون ددلاین', 'text-muted'],
        ];

        $groups = [];
        foreach ($defs as $key => [$label, $tone]) {
            if ($buckets->has($key)) {
                $groups[] = ['key' => $key, 'label' => $label, 'tone' => $tone, 'tasks' => $buckets[$key]];
            }
        }

        return $groups;
    }

    public function toggle(string $group): void
    {
        $this->expanded[$group] = ! ($this->expanded[$group] ?? false);
    }

    public function setStatus(int $id, string $status): void
    {
        $task = Task::query()->findOrFail($id);
        Gate::authorize('update', $task);

        $to = TaskStatus::tryFrom($status);
        abort_unless($to, 422);

        (new ChangeTaskStatus)->handle(auth()->user(), $task, $to);

        unset($this->groups);
        $this->dispatch('task-updated');
    }
};
?>

@php
    $groups = $this->groups;
    $editable = auth()->user()->can('tasks.write');
    $limit = 8;
    $filterLabels = ['overdue' => 'عقب‌افتاده', 'week' => 'ددلاین این هفته', 'done' => 'انجام‌شده این هفته'];
@endphp

<div class="card">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-4">
        <h2 class="text-base font-bold">تسک‌های من</h2>

        @if ($filter !== '' && isset($filterLabels[$filter]))
            <button
                type="button"
                wire:click="$parent.setFilter('')"
                class="inline-flex h-7 items-center gap-1.5 rounded-full bg-primary-soft ps-3 pe-2 text-xs font-medium text-primary transition hover:bg-primary/20"
            >
                {{ $filterLabels[$filter] }}
                <x-ui.icon name="x" class="size-3.5" />
            </button>
        @endif
    </div>

    @forelse ($groups as $group)
        @php
            $isOpen = $expanded[$group['key']] ?? false;
            $count = $group['tasks']->count();
            $items = $isOpen ? $group['tasks'] : $group['tasks']->take($limit);
        @endphp

        <section wire:key="group-{{ $group['key'] }}">
            <div class="flex items-center justify-between border-b border-line bg-canvas/50 px-5 py-2">
                <h3 class="text-xs font-bold {{ $group['tone'] }}">{{ $group['label'] }}</h3>
                <span class="tabular text-xs text-muted">{{ $count }}</span>
            </div>

            <div class="divide-y divide-line border-b border-line last:border-b-0">
                @foreach ($items as $task)
                    <x-task.row wire:key="task-{{ $task->id }}" :task="$task" :editable="$editable" />
                @endforeach
            </div>

            @if ($count > $limit)
                <button
                    type="button"
                    wire:click="toggle('{{ $group['key'] }}')"
                    class="w-full border-b border-line px-5 py-2.5 text-center text-sm text-muted transition last:border-b-0 hover:bg-line/30 hover:text-primary"
                >
                    {{ $isOpen ? 'نمایش کمتر' : 'نمایش همه ('.$count.')' }}
                </button>
            @endif
        </section>
    @empty
        @if ($filter === '')
            <x-ui.empty-state title="همه‌چیز انجام شده" tone="success">
                تسک بازی نداری. وقتی چیزی به تو داده بشه، اینجا می‌بینی.
            </x-ui.empty-state>
        @else
            <x-ui.empty-state title="موردی پیدا نشد" icon="list">
                برای این فیلتر تسکی نداری.
            </x-ui.empty-state>
        @endif
    @endforelse
</div>
