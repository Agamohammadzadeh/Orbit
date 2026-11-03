<?php

use App\Queries\ReportsQuery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('گزارش‌ها')] class extends Component {
    use WithPagination;

    /** '' = همه‌ی پروژه‌ها | شناسه‌ی پروژه */
    #[Url(except: '')]
    public string $project = '';

    /** 7 | 30 | 90 | 365 */
    #[Url(except: '30')]
    public string $days = '30';

    public function mount(): void
    {
        if (! in_array($this->days, ['7', '30', '90', '365'], true)) {
            $this->days = '30';
        }
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['project', 'days'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function report(): ReportsQuery
    {
        return new ReportsQuery(
            auth()->user(),
            ctype_digit($this->project) ? (int) $this->project : null,
            (int) $this->days,
        );
    }

    #[Computed]
    public function projectOptions(): array
    {
        return (new ReportsQuery(auth()->user()))->projects()
            ->orderBy('name')->get(['id', 'name'])->pluck('name', 'id')->all();
    }

    #[Computed]
    public function stats(): array
    {
        return $this->report->stats();
    }

    #[Computed]
    public function series(): array
    {
        return $this->report->series();
    }

    #[Computed]
    public function statuses(): array
    {
        return $this->report->statusBreakdown();
    }

    #[Computed]
    public function types(): array
    {
        return $this->report->typeBreakdown();
    }

    #[Computed]
    public function priorities(): array
    {
        return $this->report->priorityBreakdown();
    }

    #[Computed]
    public function rows()
    {
        return $this->report->projectRows(8);
    }

    #[Computed]
    public function workload()
    {
        return $this->report->workload();
    }

    #[Computed]
    public function overdue()
    {
        return $this->report->overdueTasks(10);
    }

    #[Computed]
    public function completion(): array
    {
        return $this->report->completionTimes();
    }

    #[Computed]
    public function upcoming()
    {
        return $this->report->upcoming(3);
    }

    #[Computed]
    public function upcomingCount(): int
    {
        return $this->report->upcomingCount();
    }
};
?>

@php
    $me = auth()->user();
    $stats = $this->stats;
    $series = $this->series;
    $statuses = $this->statuses;
    $rows = $this->rows;
    $hasData = $this->report->projects()->exists();
    $allScope = $me->can('reports.view-all');

    // پروژه‌هایی که کاربر می‌تواند باز کند (برای لینک‌دار بودن نام در جدول)
    $openable = \App\Models\Project::query()->visibleTo($me)->pluck('id')->flip()->all();

    $periods = ['7' => '7 روز', '30' => '30 روز', '90' => '90 روز', '365' => '1 سال'];
    $periodText = ['7' => '7 روز اخیر', '30' => '30 روز اخیر', '90' => '90 روز اخیر', '365' => 'یک سال اخیر'][$days];
    $projectOptions = ['' => 'همه‌ی پروژه‌ها'] + $this->projectOptions;

    $hours = fn (int $m): string => $m > 0 ? rtrim(rtrim(number_format($m / 60, 1), '0'), '.').' ساعت' : '—';

    $statusTotal = max(1, array_sum($statuses));
    $chartMax = max(1, ...array_map(fn ($b) => max($b['created'], $b['done']), $series));
    $every = max(1, (int) ceil(count($series) / 10));
    $typeMax = max(1, ...array_values($this->types));
    $prioMax = max(1, ...array_values($this->priorities));
    $load = $this->workload;
    $loadMax = max(1, (int) $load->max('open_count'));

    $completion = $this->completion;
    $compMax = max(1, ...array_column($completion['buckets'], 'count'));
    $createdSum = array_sum(array_column($series, 'created'));
    $doneSum = array_sum(array_column($series, 'done'));
    $upcomingTotal = $this->upcomingCount;
@endphp

<div class="space-y-6">
    {{-- سربرگ --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">گزارش‌ها</h1>
            <p class="mt-1 text-sm text-muted">
                {{ $allScope ? 'همه‌ی پروژه‌های فعال' : 'پروژه‌های من' }}
                · {{ $periodText }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-ui.select wire:model.live="project" :options="$projectOptions" label="فیلتر پروژه" class="w-52" />

            <div class="inline-flex rounded-control border border-line bg-surface p-1" role="group" aria-label="بازه‌ی زمانی">
                @foreach ($periods as $key => $text)
                    <button
                        type="button"
                        wire:click="$set('days', '{{ $key }}')"
                        aria-pressed="{{ $days === (string) $key ? 'true' : 'false' }}"
                        @class([
                            'h-8 rounded-md px-3.5 text-sm transition',
                            'bg-primary-soft font-medium text-primary' => $days === (string) $key,
                            'text-muted hover:text-fg' => $days !== (string) $key,
                        ])
                    >{{ $text }}</button>
                @endforeach
            </div>

            @if ($hasData)
                <a href="{{ route('reports.export', array_filter(['project' => $project])) }}" class="btn-secondary">
                    <x-ui.icon name="download" class="size-4.5" />
                    خروجی CSV
                </a>
            @endif
        </div>
    </div>

    @if (! $hasData)
        <div class="card">
            <x-ui.empty-state title="داده‌ای برای گزارش نیست" icon="chart">
                {{ $project !== '' ? 'پروژه‌ی انتخاب‌شده در دسترس نیست.' : 'هنوز عضو پروژه‌ی فعالی نیستی.' }}
            </x-ui.empty-state>
        </div>
    @else
        {{-- آمار --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-ui.stat-card
                tag="div"
                label="تسک باز"
                :value="$stats['open']"
                icon="list"
                tone="primary"
                :hint="'از '.$stats['total'].' تسک'"
            />
            <x-ui.stat-card
                tag="div"
                label="انجام‌شده ({{ $periodText }})"
                :value="$stats['done']"
                icon="check-circle"
                tone="success"
                :hint="$stats['doneDelta'] === null ? null : ($stats['doneDelta'] > 0 ? '+' : '').$stats['doneDelta'].'٪ نسبت به بازه‌ی قبل'"
                :hint-tone="$stats['doneDelta'] === null || $stats['doneDelta'] === 0 ? 'muted' : ($stats['doneDelta'] > 0 ? 'success' : 'danger')"
            />
            <x-ui.stat-card
                tag="div"
                label="عقب‌افتاده"
                :value="$stats['overdue']"
                icon="alert-triangle"
                :tone="$stats['overdue'] > 0 ? 'danger' : 'success'"
            />
            <x-ui.stat-card
                tag="div"
                label="زمان ثبت‌شده"
                :value="$hours($stats['minutes'])"
                icon="clock"
                :hint="$stats['avgDays'] !== null ? 'میانگین تکمیل تسک: '.$stats['avgDays'].' روز' : null"
            />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-stretch">
            {{-- ستون اصلی: نمودار و دو کارت آماری (کارت‌های پایین کش می‌آیند تا هم‌قد ستون کناری شوند) --}}
            <div class="flex flex-col gap-6 lg:col-span-2">
                {{-- نمودار --}}
                <div class="card p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold">ساخته‌شده و انجام‌شده</h2>
                            <p class="mt-0.5 text-xs text-muted">
                                {{ $periodText }}
                                · <span class="tabular">{{ $createdSum }}</span> ساخته‌شده
                                · <span class="tabular">{{ $doneSum }}</span> انجام‌شده
                            </p>
                        </div>
                        <div class="flex items-center gap-4 text-xs text-muted">
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-line"></span>ساخته‌شده</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-primary"></span>انجام‌شده</span>
                        </div>
                    </div>

                    <div class="relative mt-5">
                        {{-- خط‌های راهنما --}}
                        <div class="pointer-events-none absolute inset-x-0 top-0 flex h-48 flex-col justify-between" aria-hidden="true">
                            @foreach ([$chartMax, (int) round($chartMax * 2 / 3), (int) round($chartMax / 3), 0] as $tick)
                                <div class="flex items-center gap-2">
                                    <span class="tabular w-6 shrink-0 text-[0.625rem] text-muted">{{ $tick }}</span>
                                    <span class="h-px flex-1 bg-line/70"></span>
                                </div>
                            @endforeach
                        </div>

                        <div class="relative flex h-48 items-end gap-1 ps-8" role="img" aria-label="نمودار تسک‌های ساخته‌شده و انجام‌شده">
                            @foreach ($series as $b)
                                @php
                                    $hc = $b['created'] > 0 ? max(3, (int) round($b['created'] / $chartMax * 100)) : 0;
                                    $hd = $b['done'] > 0 ? max(3, (int) round($b['done'] / $chartMax * 100)) : 0;
                                @endphp
                                <div
                                    class="flex h-full flex-1 items-end justify-center gap-px"
                                    title="{{ $b['tip'] }}: {{ $b['created'] }} ساخته‌شده، {{ $b['done'] }} انجام‌شده"
                                >
                                    <div class="w-full max-w-3 rounded-t bg-line transition-[height] duration-300" style="height: {{ $hc }}%"></div>
                                    <div class="w-full max-w-3 rounded-t bg-primary transition-[height] duration-300" style="height: {{ $hd }}%"></div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-2 flex gap-1 ps-8 text-[0.6875rem] text-muted">
                            @foreach ($series as $i => $b)
                                <span class="tabular flex-1 truncate text-center">{{ $i % $every === 0 ? $b['label'] : '' }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid flex-1 gap-6 sm:grid-cols-2">
                    {{-- سرعت تکمیل --}}
                    <div class="card flex flex-col">
                        <div class="border-b border-line px-5 py-4">
                            <h2 class="text-base font-bold">سرعت تکمیل</h2>
                            <p class="mt-0.5 text-xs text-muted">
                                @if ($completion['median'] !== null)
                                    میانه‌ی مدت تکمیل:
                                    <span class="tabular font-medium text-fg">{{ $completion['median'] }}</span> روز
                                @else
                                    مدت تکمیل تسک‌های انجام‌شده
                                @endif
                            </p>
                        </div>

                        @if ($completion['total'] === 0)
                            <x-ui.empty-state title="هنوز تسک انجام‌شده‌ای نیست" icon="clock" class="my-auto">
                                در {{ $periodText }} تسکی تمام نشده است.
                            </x-ui.empty-state>
                        @else
                            <ul class="space-y-3.5 px-5 py-5">
                                @foreach ($completion['buckets'] as $b)
                                    <li class="flex items-center gap-3 text-sm">
                                        <span class="w-24 shrink-0 text-muted">{{ $b['label'] }}</span>
                                        <span class="h-2 flex-1 overflow-hidden rounded-full bg-line">
                                            <span class="block h-full rounded-full bg-primary" style="width: {{ $b['count'] / $compMax * 100 }}%"></span>
                                        </span>
                                        <span class="tabular w-7 text-end font-medium">{{ $b['count'] }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            <p class="tabular mt-auto border-t border-line bg-canvas/50 px-5 py-3 text-xs text-muted">
                                از ساخت تا تکمیل، روی {{ $completion['total'] }} تسک
                            </p>
                        @endif
                    </div>

                    {{-- ددلاین‌های پیش رو --}}
                    <div class="card flex flex-col">
                        <div class="border-b border-line px-5 py-4">
                            <h2 class="text-base font-bold">ددلاین‌های پیش رو</h2>
                            <p class="mt-0.5 text-xs text-muted">تسک‌های باز در ۱۴ روز آینده</p>
                        </div>

                        @if ($this->upcoming->isEmpty())
                            <x-ui.empty-state title="ددلاینی در پیش نیست" tone="success" class="my-auto">
                                برای ۱۴ روز آینده تسک بازی با ددلاین نداری.
                            </x-ui.empty-state>
                        @else
                            <ul class="divide-y divide-line">
                                @foreach ($this->upcoming as $t)
                                    <li wire:key="upcoming-{{ $t->id }}">
                                        <a href="{{ route('tasks.show', $t) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 transition hover:bg-line/30">
                                            <span class="size-2 shrink-0 rounded-[3px]" style="background-color: {{ $t->project->color ?? '#5B5BD6' }}"></span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium">{{ $t->title }}</p>
                                                <p class="mt-0.5 truncate text-xs text-muted">{{ $t->project->name }} · {{ $t->assignee?->name ?? 'بدون مسئول' }}</p>
                                            </div>
                                            <x-task.due :task="$t" class="shrink-0" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($upcomingTotal > $this->upcoming->count())
                                <p class="tabular mt-auto border-t border-line bg-canvas/50 px-5 py-3 text-xs text-muted">
                                    و {{ $upcomingTotal - $this->upcoming->count() }} مورد دیگر
                                </p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- ستون کناری: وضعیت، نوع و اولویت --}}
            <div class="space-y-6">
                <div class="card p-6">
                    <h2 class="text-base font-bold">وضعیت تسک‌ها</h2>

                    <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-line" role="img" aria-label="توزیع وضعیت تسک‌ها">
                        @foreach (\App\Enums\TaskStatus::cases() as $s)
                            @if ($statuses[$s->value] > 0)
                                <div style="width: {{ $statuses[$s->value] / $statusTotal * 100 }}%; background-color: {{ $s->color() }}" title="{{ $s->label() }}: {{ $statuses[$s->value] }}"></div>
                            @endif
                        @endforeach
                    </div>

                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach (\App\Enums\TaskStatus::cases() as $s)
                            <li class="flex items-center gap-2.5">
                                <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $s->color() }}"></span>
                                <span class="flex-1">{{ $s->label() }}</span>
                                <span class="tabular font-medium">{{ $statuses[$s->value] }}</span>
                                <span class="tabular w-10 text-end text-xs text-muted">{{ (int) round($statuses[$s->value] / $statusTotal * 100) }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="card space-y-5 p-6 pb-8.5">
                    <div>
                        <h2 class="text-base font-bold">تسک‌های باز</h2>
                        <p class="mt-0.5 text-xs text-muted">بر اساس نوع و اولویت</p>
                    </div>

                    <ul class="space-y-2.5">
                        @foreach (\App\Enums\TaskType::cases() as $t)
                            <li class="flex items-center gap-3 text-sm">
                                <span class="w-14 shrink-0" style="color: {{ $t->color() }}">{{ $t->label() }}</span>
                                <span class="h-2 flex-1 overflow-hidden rounded-full bg-line">
                                    <span class="block h-full rounded-full" style="width: {{ $this->types[$t->value] / $typeMax * 100 }}%; background-color: {{ $t->color() }}"></span>
                                </span>
                                <span class="tabular w-7 text-end font-medium">{{ $this->types[$t->value] }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <ul class="space-y-2.5 border-t border-line pt-5">
                        @foreach ([\App\Enums\TaskPriority::Urgent, \App\Enums\TaskPriority::High, \App\Enums\TaskPriority::Medium, \App\Enums\TaskPriority::Low] as $p)
                            <li class="flex items-center gap-3 text-sm">
                                <span class="w-14 shrink-0" style="color: {{ $p->color() }}">{{ $p->label() }}</span>
                                <span class="h-2 flex-1 overflow-hidden rounded-full bg-line">
                                    <span class="block h-full rounded-full" style="width: {{ $this->priorities[$p->value] / $prioMax * 100 }}%; background-color: {{ $p->color() }}"></span>
                                </span>
                                <span class="tabular w-7 text-end font-medium">{{ $this->priorities[$p->value] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{-- پروژه‌ها --}}
        <div class="card">
            <div class="border-b border-line px-5 py-4">
                <h2 class="text-base font-bold">پروژه‌ها</h2>
                <p class="mt-0.5 text-xs text-muted">زمان ثبت‌شده مربوط به {{ $periodText }} است</p>
            </div>

            @php $cols = 'md:grid-cols-[minmax(0,2fr)_minmax(0,1.6fr)_4rem_5rem_5.5rem_6rem]'; @endphp

            <div class="hidden gap-4 border-b border-line px-5 py-3 text-xs font-medium text-muted md:grid {{ $cols }}">
                <div>پروژه</div>
                <div>پیشرفت</div>
                <div>باز</div>
                <div>عقب‌افتاده</div>
                <div>زمان</div>
                <div>پایان</div>
            </div>

            <ul class="divide-y divide-line">
                @foreach ($rows as $p)
                    @php
                        $open = $p->tasks_count - $p->done_count;
                        $pct = $p->tasks_count > 0 ? (int) round($p->done_count / $p->tasks_count * 100) : 0;
                        $days_left = \App\Support\Dates::daysFromToday($p->end_date);
                        $late = $p->status !== \App\Enums\ProjectStatus::Done && $days_left !== null && $days_left < 0;
                    @endphp

                    <li wire:key="report-project-{{ $p->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3.5 md:grid {{ $cols }}">
                        <div class="flex min-w-0 items-center gap-3 max-md:basis-full">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg text-sm font-bold text-white" style="background-color: {{ $p->color ?? '#5B5BD6' }}">{{ mb_substr($p->name, 0, 1) }}</span>
                            <div class="min-w-0">
                                @if (isset($openable[$p->id]))
                                    <a href="{{ route('projects.show', $p) }}" wire:navigate class="block truncate text-sm font-medium transition hover:text-primary">{{ $p->name }}</a>
                                @else
                                    <span class="block truncate text-sm font-medium">{{ $p->name }}</span>
                                @endif
                                <p class="truncate text-xs text-muted">{{ $p->client?->name ?? 'بدون مشتری' }} · <span style="color: {{ $p->status->color() }}">{{ $p->status->label() }}</span></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 max-md:w-48">
                            <x-ui.progress :value="$pct" :tone="$pct >= 100 ? 'success' : 'primary'" class="flex-1" />
                            <span class="tabular w-9 shrink-0 text-xs text-muted">{{ $pct }}%</span>
                        </div>

                        <div class="tabular text-sm"><span class="text-muted md:hidden">باز: </span>{{ $open }}</div>

                        <div class="tabular text-sm {{ $p->overdue_count > 0 ? 'font-medium text-danger' : 'text-muted' }}">
                            <span class="md:hidden">عقب‌افتاده: </span>{{ $p->overdue_count }}
                        </div>

                        <div class="text-sm text-muted">{{ $hours($p->minutes) }}</div>

                        <div class="text-xs {{ $late ? 'font-medium text-danger' : 'text-muted' }}">{{ \App\Support\Dates::short($p->end_date) }}</div>
                    </li>
                @endforeach
            </ul>

            <x-ui.pagination :paginator="$rows" />
        </div>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
            {{-- حجم کار --}}
            <div class="card">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-base font-bold">حجم کار اعضا</h2>
                    <p class="mt-0.5 text-xs text-muted">تسک باز، انجام‌شده و زمان ثبت‌شده در {{ $periodText }}</p>
                </div>

                @if ($load->isEmpty())
                    <x-ui.empty-state title="عضوی نیست" icon="users" />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($load as $u)
                            <li wire:key="load-{{ $u->id }}" class="flex items-center gap-3 px-5 py-3.5">
                                <x-ui.avatar :user="$u" :size="32" />

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="truncate text-sm font-medium">{{ $u->name }}</span>
                                        <span class="tabular shrink-0 text-xs text-muted">{{ $u->open_count }} باز</span>
                                    </div>
                                    <x-ui.progress :value="(int) round($u->open_count / $loadMax * 100)" :tone="$u->overdue_count > 0 ? 'danger' : 'primary'" class="mt-2" />
                                    <p class="tabular mt-1.5 text-xs text-muted">
                                        {{ $u->done_count }} انجام‌شده · {{ $hours($u->minutes) }}
                                    </p>
                                </div>

                                @if ($u->overdue_count > 0)
                                    <span class="tabular shrink-0 rounded-full bg-danger/10 px-2 py-0.5 text-xs font-medium text-danger" title="عقب‌افتاده">{{ $u->overdue_count }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @php $unassigned = $this->report->unassignedOpen(); @endphp
                    @if ($unassigned > 0)
                        <div class="flex items-center justify-between border-t border-line bg-canvas/50 px-5 py-3 text-sm">
                            <span class="text-muted">تسک باز بدون مسئول</span>
                            <span class="tabular font-medium">{{ $unassigned }}</span>
                        </div>
                    @endif
                @endif
            </div>

            {{-- عقب‌افتاده‌ها --}}
            <div class="card">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-base font-bold">عقب‌افتاده‌ها</h2>
                    <p class="mt-0.5 text-xs text-muted">قدیمی‌ترین‌ها</p>
                </div>

                @if ($this->overdue->isEmpty())
                    <x-ui.empty-state title="تسک عقب‌افتاده‌ای نیست" tone="success">همه‌ی تسک‌ها سر وقت هستند.</x-ui.empty-state>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($this->overdue as $t)
                            @php $late = (int) abs(today()->diffInDays($t->due_date, false)); @endphp
                            <li wire:key="overdue-{{ $t->id }}">
                                <a href="{{ route('tasks.show', $t) }}" wire:navigate class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-line/30">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">{{ $t->title }}</p>
                                        <p class="mt-0.5 truncate text-xs text-muted">{{ $t->project->name }} · {{ $t->assignee?->name ?? 'بدون مسئول' }}</p>
                                    </div>
                                    <span class="tabular shrink-0 text-xs font-medium text-danger">{{ $late }} روز پیش</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</div>
