<?php

use App\Queries\DashboardQuery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('داشبورد')] class extends Component {
    /** فیلتر لیست تسک‌ها: '' | overdue | week | done */
    #[Url(except: '')]
    public string $filter = '';

    /** me | team (نمای تیم فقط با دسترسی «گزارش همه‌ی پروژه‌ها») */
    #[Url(except: 'me')]
    public string $view = 'me';

    public function mount(): void
    {
        if (! in_array($this->filter, ['', 'overdue', 'week', 'done'], true)) {
            $this->filter = '';
        }

        if ($this->view === 'team' && ! auth()->user()->can('reports.view-all')) {
            $this->view = 'me';
        }
    }

    #[Computed]
    public function stats(): array
    {
        return (new DashboardQuery)->stats(auth()->user());
    }

    /** کلیک دوباره روی همان کارت، فیلتر را برمی‌دارد */
    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['overdue', 'week', 'done'], true) && $this->filter !== $filter
            ? $filter
            : '';
    }

    public function setView(string $view): void
    {
        $this->view = $view === 'team' && auth()->user()->can('reports.view-all') ? 'team' : 'me';
    }

    /** وقتی تسکی تغییر کرد، آمار دوباره محاسبه می‌شود */
    #[On('task-updated')]
    public function refreshStats(): void
    {
        unset($this->stats);
    }
};
?>

@php
    $me = auth()->user();
    $stats = $this->stats;
    $canTeam = $me->can('reports.view-all');
    $canWork = $me->can('tasks.write');
    $inTeam = $view === 'team' && $canTeam;

    $hour = now()->hour;
    $hello = match (true) {
        $hour < 5 => 'شب بخیر',
        $hour < 12 => 'صبح بخیر',
        $hour < 17 => 'ظهر بخیر',
        $hour < 20 => 'عصر بخیر',
        default => 'شب بخیر',
    };
    $first = explode(' ', trim($me->name))[0];

    $dateLabel = config('orbit.calendar') === 'jalali'
        ? \Morilog\Jalali\Jalalian::now()->format('%A، %d %B %Y')
        : now()->translatedFormat('l، j F Y');

    $parts = [];
    if ($stats['today'] > 0) {
        $parts[] = $stats['today'].' تسک برای امروز';
    }
    if ($stats['overdue'] > 0) {
        $parts[] = $stats['overdue'].' تسک عقب‌افتاده';
    }
    $summary = $parts ? implode('، ', $parts) : 'تسک عقب‌افتاده یا ددلاینِ امروز نداری.';
@endphp

<div class="space-y-6">
    {{-- سربرگ --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-muted">{{ $dateLabel }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $hello }}، {{ $first }}</h1>
            @if (! $inTeam && $canWork)
                <p class="mt-1 text-sm text-muted">{{ $summary }}</p>
            @endif
        </div>

        @if ($canTeam)
            <div class="inline-flex rounded-control border border-line bg-surface p-1" role="group" aria-label="نوع نمایش">
                @foreach (['me' => 'من', 'team' => 'تیم'] as $key => $text)
                    <button
                        type="button"
                        wire:click="setView('{{ $key }}')"
                        aria-pressed="{{ $view === $key ? 'true' : 'false' }}"
                        @class([
                            'h-8 rounded-md px-4 text-sm transition',
                            'bg-primary-soft font-medium text-primary' => $view === $key,
                            'text-muted hover:text-fg' => $view !== $key,
                        ])
                    >{{ $text }}</button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($inTeam)
        <livewire:dashboard.team-overview />
    @else
        {{-- کارت‌های آمار (کلیک = فیلتر لیست تسک‌ها) --}}
        @if ($canWork)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-ui.stat-card
                    label="تسک‌های باز من"
                    :value="$stats['open']"
                    icon="list"
                    tone="primary"
                    :active="$filter === ''"
                    wire:click="setFilter('')"
                />
                <x-ui.stat-card
                    label="عقب‌افتاده"
                    :value="$stats['overdue']"
                    icon="alert-triangle"
                    :tone="$stats['overdue'] > 0 ? 'danger' : 'success'"
                    :active="$filter === 'overdue'"
                    wire:click="setFilter('overdue')"
                />
                <x-ui.stat-card
                    label="ددلاین این هفته"
                    :value="$stats['week']"
                    icon="calendar"
                    :active="$filter === 'week'"
                    wire:click="setFilter('week')"
                />
                <x-ui.stat-card
                    label="انجام‌شده این هفته"
                    :value="$stats['done']"
                    icon="check-circle"
                    tone="success"
                    :active="$filter === 'done'"
                    wire:click="setFilter('done')"
                />
            </div>
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-3">
            @if ($canWork)
                <div class="lg:col-span-2">
                    <livewire:dashboard.my-tasks :filter="$filter" />
                </div>

                <div class="space-y-6">
                    <livewire:dashboard.my-projects />
                    <livewire:dashboard.recent-notifications />
                    <livewire:dashboard.time-week />
                </div>
            @else
                {{-- بدون دسترسی نوشتن (مثلاً ناظر): پروژه‌ها و اعلان‌ها --}}
                <div class="lg:col-span-2">
                    <livewire:dashboard.my-projects />
                </div>
                <div>
                    <livewire:dashboard.recent-notifications />
                </div>
            @endif
        </div>
    @endif
</div>
