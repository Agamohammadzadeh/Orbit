<?php

use App\Actions\Tasks\ChangeTaskStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

new #[Title('تقویم')] class extends Component {
    /** ماه نمایش‌داده‌شده به‌صورت YYYY-MM (شمسی یا میلادی، طبق تنظیم برنامه)؛ خالی = ماه جاری */
    #[Url(as: 'm', except: '')]
    public string $month = '';

    /** mine = تسک‌های من | all = همه‌ی تسک‌هایی که می‌بینم */
    #[Url(except: 'mine')]
    public string $scope = 'mine';

    #[Url(except: '')]
    public string $project = '';

    /** all | open */
    #[Url(except: 'all')]
    public string $show = 'all';

    /** روز انتخاب‌شده (Y-m-d) برای پنجره‌ی جزئیات */
    public ?string $day = null;

    public function mount(): void
    {
        if (! in_array($this->scope, ['mine', 'all'], true)) {
            $this->scope = 'mine';
        }
        if (! in_array($this->show, ['all', 'open'], true)) {
            $this->show = 'all';
        }
    }

    /* ---------------- ماه و شبکه ---------------- */

    private function jalali(): bool
    {
        return config('orbit.calendar') === 'jalali';
    }

    /** @return array{0:int,1:int} */
    private function currentYm(): array
    {
        $now = today();

        if ($this->jalali()) {
            $j = Jalalian::fromDateTime($now);

            return [$j->getYear(), $j->getMonth()];
        }

        return [$now->year, $now->month];
    }

    /** @return array{0:int,1:int} */
    private function ym(): array
    {
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $this->month, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $ok = $this->jalali() ? ($y >= 1300 && $y <= 1600) : ($y >= 1900 && $y <= 2200);

            if ($ok && $mo >= 1 && $mo <= 12) {
                return [$y, $mo];
            }
        }

        return $this->currentYm();
    }

    private function firstOf(int $y, int $m): CarbonImmutable
    {
        return $this->jalali()
            ? CarbonImmutable::instance((new Jalalian($y, $m, 1))->toCarbon())->startOfDay()
            : CarbonImmutable::create($y, $m, 1)->startOfDay();
    }

    private function shift(int $by): void
    {
        [$y, $m] = $this->ym();

        $m += $by;
        if ($m > 12) {
            [$m, $y] = [1, $y + 1];
        }
        if ($m < 1) {
            [$m, $y] = [12, $y - 1];
        }

        $this->month = sprintf('%04d-%02d', $y, $m);
    }

    public function goPrev(): void
    {
        $this->shift(-1);
    }

    public function goNext(): void
    {
        $this->shift(1);
    }

    public function goToday(): void
    {
        $this->month = '';
    }

    #[Computed]
    public function period(): array
    {
        [$y, $m] = $this->ym();

        $first = $this->firstOf($y, $m);
        [$ny, $nm] = $m === 12 ? [$y + 1, 1] : [$y, $m + 1];
        $days = (int) round($first->diffInDays($this->firstOf($ny, $nm)));

        $title = $this->jalali()
            ? (new Jalalian($y, $m, 1))->format('%B %Y')
            : $first->translatedFormat('F Y');

        // هفته از شنبه شروع می‌شود (dayOfWeek: یکشنبه = ۰ ... شنبه = ۶)
        $offset = ($first->dayOfWeek + 1) % 7;
        $weeks = (int) ceil(($offset + $days) / 7);
        $start = $first->subDays($offset);

        $cells = [];
        for ($i = 0; $i < $weeks * 7; $i++) {
            $d = $start->addDays($i);

            $cells[] = [
                'date' => $d,
                'key' => $d->format('Y-m-d'),
                'label' => $this->jalali() ? Jalalian::fromDateTime($d)->getDay() : $d->day,
                'inMonth' => $i >= $offset && $i < $offset + $days,
                'today' => $d->isSameDay(today()),
                'weekend' => $i % 7 === 6,
            ];
        }

        return [
            'title' => $title,
            'cells' => $cells,
            'isCurrent' => [$y, $m] === $this->currentYm(),
        ];
    }

    /* ---------------- داده‌ها ---------------- */

    #[Computed]
    public function projects(): array
    {
        return Project::query()
            ->visibleTo(auth()->user())
            ->notArchived()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array{tasks:\Illuminate\Support\Collection,deadlines:\Illuminate\Support\Collection} */
    #[Computed]
    public function data(): array
    {
        $user = auth()->user();
        $cells = $this->period['cells'];
        $from = $cells[0]['key'];
        $to = $cells[array_key_last($cells)]['key'];
        $pid = ctype_digit($this->project) ? (int) $this->project : null;

        $tasks = Task::query()
            ->visibleTo($user)
            ->whereHas('project', fn ($p) => $p->notArchived())
            ->whereBetween('due_date', [$from, $to])
            ->when($this->scope === 'mine', fn ($q) => $q->where('assignee_id', $user->id))
            ->when($pid, fn ($q) => $q->where('project_id', $pid))
            ->when($this->show === 'open', fn ($q) => $q->open())
            ->with('project:id,name,color')
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->limit(600)
            ->get()
            ->groupBy(fn (Task $t) => $t->due_date->format('Y-m-d'));

        $deadlines = Project::query()
            ->visibleTo($user)
            ->notArchived()
            ->withStatus(ProjectStatus::Active)
            ->whereBetween('end_date', [$from, $to])
            ->when($pid, fn ($q) => $q->whereKey($pid))
            ->get(['id', 'name', 'slug', 'color', 'end_date'])
            ->groupBy(fn (Project $p) => $p->end_date->format('Y-m-d'));

        return ['tasks' => $tasks, 'deadlines' => $deadlines];
    }

    #[On('task-saved')]
    public function refresh(): void
    {
        unset($this->data);
    }

    /* ---------------- روز ---------------- */

    public function openDay(string $date): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false) {
            $this->day = $date;
        }
    }

    public function closeDay(): void
    {
        $this->day = null;
    }

    /** ساخت تسک با ددلاین روز انتخاب‌شده (فرم سراسری تسک) */
    public function newTask(string $date): void
    {
        $this->dispatch(
            'open-task-form',
            dueDate: $date,
            projectId: ctype_digit($this->project) ? (int) $this->project : null,
        );

        $this->closeDay();
    }

    public function setStatus(int $id, string $status): void
    {
        $task = Task::query()->findOrFail($id);
        Gate::authorize('update', $task);

        $to = TaskStatus::tryFrom($status);
        abort_unless($to, 422);

        (new ChangeTaskStatus)->handle(auth()->user(), $task, $to);

        unset($this->data);
        $this->dispatch('task-updated');
    }
};
?>

@php
    $period = $this->period;
    $data = $this->data;
    $canWrite = auth()->user()->can('tasks.write');

    $weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
    $projectOptions = ['' => 'همه‌ی پروژه‌ها'] + $this->projects;
    $showOptions = ['all' => 'همه‌ی تسک‌ها', 'open' => 'فقط باز'];

    $dayDate = $day ? \Carbon\CarbonImmutable::parse($day) : null;
    $dayTasks = $day ? $data['tasks']->get($day, collect()) : collect();
    $dayDeadlines = $day ? $data['deadlines']->get($day, collect()) : collect();
    $dayTitle = $dayDate
        ? (config('orbit.calendar') === 'jalali'
            ? \Morilog\Jalali\Jalalian::fromDateTime($dayDate)->format('%A، %d %B %Y')
            : $dayDate->translatedFormat('l، j F Y'))
        : '';
@endphp

<div class="space-y-4">
    {{-- سربرگ و ناوبری --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight">تقویم</h1>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" wire:click="goPrev" class="flex size-10 items-center justify-center rounded-control border border-line bg-surface text-muted transition hover:text-fg" aria-label="ماه قبل">
                <x-ui.icon name="chevron-right" class="size-5" />
            </button>

            <span class="min-w-36 text-center text-base font-bold">{{ $period['title'] }}</span>

            <button type="button" wire:click="goNext" class="flex size-10 items-center justify-center rounded-control border border-line bg-surface text-muted transition hover:text-fg" aria-label="ماه بعد">
                <x-ui.icon name="chevron-left" class="size-5" />
            </button>

            @unless ($period['isCurrent'])
                <button type="button" wire:click="goToday" class="btn-secondary h-10! px-4! text-sm!">امروز</button>
            @endunless
        </div>
    </div>

    {{-- فیلترها --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-control border border-line bg-surface p-1" role="group" aria-label="نوع تسک‌ها">
            @foreach (['mine' => 'تسک‌های من', 'all' => 'همه'] as $key => $text)
                <button
                    type="button"
                    wire:click="$set('scope', '{{ $key }}')"
                    aria-pressed="{{ $scope === $key ? 'true' : 'false' }}"
                    @class([
                        'h-8 rounded-md px-4 text-sm transition',
                        'bg-primary-soft font-medium text-primary' => $scope === $key,
                        'text-muted hover:text-fg' => $scope !== $key,
                    ])
                >{{ $text }}</button>
            @endforeach
        </div>

        <x-ui.select wire:model.live="project" :options="$projectOptions" label="فیلتر پروژه" class="w-52" />
        <x-ui.select wire:model.live="show" :options="$showOptions" label="فیلتر وضعیت" class="w-44" />

        <p class="ms-auto flex items-center gap-3 text-xs text-muted max-md:hidden">
            <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-danger"></span>عقب‌افتاده</span>
            <span class="inline-flex items-center gap-1.5"><x-ui.icon name="flag" class="size-3.5 text-primary" />پایان پروژه</span>
        </p>
    </div>

    {{-- شبکه‌ی ماه --}}
    <div class="card overflow-hidden">
        <div class="grid grid-cols-7 border-b border-line bg-canvas/50">
            @foreach ($weekdays as $i => $name)
                <div @class(['py-2.5 text-center text-xs font-medium', 'text-muted' => $i !== 6, 'text-danger' => $i === 6])>
                    <span class="max-md:hidden">{{ $name }}</span>
                    <span class="md:hidden">{{ mb_substr($name, 0, 1) }}</span>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 [&>div:nth-child(7n)]:border-e-0">
            @foreach ($period['cells'] as $cell)
                <x-calendar.cell
                    wire:key="cell-{{ $cell['key'] }}"
                    :cell="$cell"
                    :tasks="$data['tasks']->get($cell['key'], collect())"
                    :deadlines="$data['deadlines']->get($cell['key'], collect())"
                />
            @endforeach
        </div>
    </div>

    {{-- پنجره‌ی جزئیات روز --}}
    @if ($dayDate)
        <x-ui.modal :title="$dayTitle" description="تسک‌ها و رویدادهای این روز" close="closeDay" size="max-w-xl">
            @if ($dayTasks->isEmpty() && $dayDeadlines->isEmpty())
                <x-ui.empty-state title="موردی برای این روز نیست" icon="calendar" class="-my-5" />
            @else
                <div class="-mx-6 -my-5 divide-y divide-line">
                    @foreach ($dayDeadlines as $p)
                        <a
                            href="{{ route('projects.show', $p) }}"
                            wire:navigate
                            wire:key="day-deadline-{{ $p->id }}"
                            class="flex items-center gap-3 px-6 py-3 transition hover:bg-line/30"
                        >
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary">
                                <x-ui.icon name="flag" class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $p->name }}</span>
                                <span class="text-xs text-muted">پایان پروژه</span>
                            </span>
                        </a>
                    @endforeach

                    @foreach ($dayTasks as $t)
                        <x-task.row :task="$t" :editable="$canWrite" wire:key="day-task-{{ $t->id }}" />
                    @endforeach
                </div>
            @endif

            <x-slot:footer>
                <button type="button" wire:click="closeDay" class="btn-secondary">بستن</button>
                @if ($canWrite)
                    <button type="button" wire:click="newTask('{{ $day }}')" class="btn-primary">
                        <x-ui.icon name="plus" class="size-4.5" />
                        تسک جدید برای این روز
                    </button>
                @endif
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
