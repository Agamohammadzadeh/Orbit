<?php

use App\Queries\DashboardQuery;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public function mount(): void
    {
        Gate::authorize('reports.view-all');
    }

    #[Computed]
    public function stats(): array
    {
        return (new DashboardQuery)->teamStats();
    }

    #[Computed]
    public function workload()
    {
        return (new DashboardQuery)->teamWorkload(8);
    }

    #[Computed]
    public function overdue()
    {
        return (new DashboardQuery)->teamOverdue(6);
    }
};
?>

@php
    $stats = $this->stats;
    $workload = $this->workload;
    $max = max(1, (int) $workload->max('open_count'));
@endphp

<div class="space-y-6">
    {{-- آمار کل تیم --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat-card tag="div" label="تسک‌های باز تیم" :value="$stats['open']" icon="list" tone="primary" />
        <x-ui.stat-card
            tag="div"
            label="عقب‌افتاده"
            :value="$stats['overdue']"
            icon="alert-triangle"
            :tone="$stats['overdue'] > 0 ? 'danger' : 'success'"
        />
        <x-ui.stat-card tag="div" label="بدون مسئول" :value="$stats['unassigned']" icon="users" />
        <x-ui.stat-card tag="div" label="انجام‌شده این هفته" :value="$stats['done']" icon="check-circle" tone="success" />
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        {{-- حجم کار اعضا --}}
        <div class="card">
            <div class="border-b border-line px-5 py-4">
                <h2 class="text-base font-bold">حجم کار اعضا</h2>
                <p class="mt-0.5 text-xs text-muted">تعداد تسک باز هر نفر</p>
            </div>

            @if ($workload->isEmpty())
                <x-ui.empty-state title="عضوی نیست" icon="users" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($workload as $u)
                        <li wire:key="load-{{ $u->id }}" class="flex items-center gap-3 px-5 py-3.5">
                            <x-ui.avatar :user="$u" :size="32" />

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="truncate text-sm font-medium">{{ $u->name }}</span>
                                    <span class="tabular shrink-0 text-xs text-muted">{{ $u->open_count }} تسک</span>
                                </div>
                                <x-ui.progress
                                    :value="(int) round($u->open_count / $max * 100)"
                                    :tone="$u->overdue_count > 0 ? 'danger' : 'primary'"
                                    class="mt-2"
                                />
                            </div>

                            @if ($u->overdue_count > 0)
                                <span class="tabular shrink-0 rounded-full bg-danger/10 px-2 py-0.5 text-xs font-medium text-danger" title="عقب‌افتاده">
                                    {{ $u->overdue_count }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- عقب‌افتاده‌های همه‌ی پروژه‌ها --}}
        <div class="card">
            <div class="border-b border-line px-5 py-4">
                <h2 class="text-base font-bold">عقب‌افتاده‌ها</h2>
                <p class="mt-0.5 text-xs text-muted">قدیمی‌ترین‌ها در همه‌ی پروژه‌ها</p>
            </div>

            @if ($this->overdue->isEmpty())
                <x-ui.empty-state title="تسک عقب‌افتاده‌ای نیست" tone="success">
                    همه‌ی تسک‌ها سر وقت هستند.
                </x-ui.empty-state>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($this->overdue as $task)
                        @php $days = (int) abs(today()->diffInDays($task->due_date, false)); @endphp
                        <li wire:key="overdue-{{ $task->id }}">
                            <a href="{{ route('tasks.show', $task) }}" wire:navigate class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-line/30">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ $task->title }}</p>
                                    <p class="mt-0.5 truncate text-xs text-muted">
                                        {{ $task->project->name }}
                                        ·
                                        {{ $task->assignee?->name ?? 'بدون مسئول' }}
                                    </p>
                                </div>
                                <span class="tabular shrink-0 text-xs font-medium text-danger">{{ $days }} روز پیش</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
