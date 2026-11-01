<?php

use App\Queries\DashboardQuery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function days(): array
    {
        return (new DashboardQuery)->weekTime(auth()->user());
    }

    /** وقتی ثبت زمان ساخته شد (مرحله‌ی بعد)، این بخش به‌روز می‌شود */
    #[On('time-logged')]
    public function refresh(): void
    {
        unset($this->days);
    }
};
?>

@php
    $days = $this->days;
    $minutes = array_column($days, 'minutes');
    $total = array_sum($minutes);
    $max = max(60, ...$minutes);
    $names = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']; // شنبه تا جمعه

    $fmt = function (int $m): string {
        if ($m <= 0) {
            return '0';
        }

        $h = intdiv($m, 60);
        $r = $m % 60;

        return collect([$h ? "{$h} ساعت" : null, $r ? "{$r} دقیقه" : null])->filter()->implode(' و ');
    };
@endphp

<div class="card p-5">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-bold">زمان ثبت‌شده</h2>
            <p class="mt-0.5 text-xs text-muted">این هفته</p>
        </div>
        <p class="tabular text-end text-sm font-bold">{{ $fmt($total) }}</p>
    </div>

    <div class="mt-5 flex h-24 items-end gap-2" role="img" aria-label="نمودار زمان ثبت‌شده در هفت روز هفته">
        @foreach ($days as $i => $d)
            @php
                $isToday = $d['date']->isSameDay(today());
                $height = $d['minutes'] > 0 ? max(6, (int) round($d['minutes'] / $max * 100)) : 0;
            @endphp
            <div class="flex h-full flex-1 flex-col items-center justify-end gap-2" title="{{ $fmt($d['minutes']) }}">
                <div class="flex w-full flex-1 items-end">
                    <div
                        @class([
                            'w-full rounded-t-md transition-[height] duration-300',
                            'bg-primary' => $isToday,
                            'bg-primary/35' => ! $isToday,
                        ])
                        style="height: {{ $height }}%"
                    ></div>
                </div>
                <span @class(['text-xs', 'font-bold text-primary' => $isToday, 'text-muted' => ! $isToday])>{{ $names[$i] }}</span>
            </div>
        @endforeach
    </div>
</div>
