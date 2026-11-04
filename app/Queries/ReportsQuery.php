<?php

namespace App\Queries;

use App\Enums\Ability;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;

/**
 * کوئری‌های صفحه‌ی گزارش‌ها.
 *
 * محدوده‌ی داده: با دسترسی «گزارش همه‌ی پروژه‌ها» همه‌ی پروژه‌های آرشیو‌نشده،
 * وگرنه فقط پروژه‌هایی که کاربر می‌بیند (عضویت). فیلتر پروژه‌ی اختیاری روی همین محدوده اعمال می‌شود.
 * $days طول بازه‌ی زمانی (۷، ۳۰، ۹۰ یا ۳۶۵ روز منتهی به امروز) است.
 */
class ReportsQuery
{
    public function __construct(
        private User $user,
        private ?int $projectId = null,
        private int $days = 30,
    ) {}

    public function from(): CarbonImmutable
    {
        return today()->subDays($this->days - 1)->startOfDay();
    }

    private function previousFrom(): CarbonImmutable
    {
        return $this->from()->subDays($this->days);
    }

    /* ---------------- محدوده ---------------- */

    public function projects(): Builder
    {
        return Project::query()
            ->notArchived()
            ->when(! $this->user->can(Ability::ReportsViewAll->value), fn ($q) => $q->visibleTo($this->user))
            ->when($this->projectId, fn ($q) => $q->whereKey($this->projectId));
    }

    public function tasks(): Builder
    {
        return Task::query()->whereIn('project_id', $this->projects()->select('projects.id'));
    }

    private function time(): Builder
    {
        return TimeEntry::query()
            ->whereIn('task_id', $this->tasks()->select('tasks.id'))
            ->where('worked_on', '>=', $this->from()->toDateString());
    }

    /* ---------------- آمار کلی ---------------- */

    /** @return array{total:int,open:int,overdue:int,done:int,doneDelta:?int,minutes:int,avgDays:?float} */
    public function stats(): array
    {
        $from = $this->from();

        $done = $this->tasks()->inStatus(TaskStatus::Done)->where('completed_at', '>=', $from)->count();

        $previous = $this->tasks()
            ->inStatus(TaskStatus::Done)
            ->where('completed_at', '>=', $this->previousFrom())
            ->where('completed_at', '<', $from)
            ->count();

        $avg = $this->tasks()
            ->inStatus(TaskStatus::Done)
            ->where('completed_at', '>=', $from)
            ->get(['id', 'created_at', 'completed_at'])
            ->avg(fn (Task $t) => $t->created_at->diffInDays($t->completed_at, false));

        return [
            'total' => $this->tasks()->count(),
            'open' => $this->tasks()->open()->count(),
            'overdue' => $this->tasks()->overdue()->count(),
            'done' => $done,
            'doneDelta' => $previous > 0 ? (int) round(($done - $previous) / $previous * 100) : null,
            'minutes' => (int) $this->time()->sum('minutes'),
            'avgDays' => $avg !== null ? round(max(0, (float) $avg), 1) : null,
        ];
    }

    /** تعداد همه‌ی تسک‌ها در هر وضعیت (صفرها هم هستند) @return array<string,int> */
    public function statusBreakdown(): array
    {
        $counts = $this->tasks()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(TaskStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all();
    }

    /** تسک‌های باز بر اساس نوع @return array<string,int> */
    public function typeBreakdown(): array
    {
        $counts = $this->tasks()->open()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        return collect(TaskType::cases())->mapWithKeys(fn ($t) => [$t->value => (int) ($counts[$t->value] ?? 0)])->all();
    }

    /** تسک‌های باز بر اساس اولویت @return array<string,int> */
    public function priorityBreakdown(): array
    {
        $counts = $this->tasks()->open()->selectRaw('priority, count(*) as total')->groupBy('priority')->pluck('total', 'priority');

        return collect([TaskPriority::Urgent, TaskPriority::High, TaskPriority::Medium, TaskPriority::Low])
            ->mapWithKeys(fn ($p) => [$p->value => (int) ($counts[$p->value] ?? 0)])
            ->all();
    }

    /* ---------------- نمودار ---------------- */

    /**
     * ساخته‌شده و انجام‌شده در بازه‌های زمانی: روزانه (تا ۳۱ روز)، هفتگی (تا ۱۰۰ روز)، وگرنه ۳۰ روزه.
     *
     * @return array<int,array{label:string,tip:string,created:int,done:int}>
     */
    public function series(): array
    {
        $from = $this->from();
        $size = $this->days <= 31 ? 1 : ($this->days <= 100 ? 7 : 30);
        $count = (int) ceil($this->days / $size);

        $buckets = [];
        for ($i = 0; $i < $count; $i++) {
            $start = $from->addDays($i * $size);
            $end = $from->addDays(min(($i + 1) * $size, $this->days) - 1);

            $buckets[] = [
                'label' => $this->label($start, $size),
                'tip' => $size === 1 ? $this->short($start) : $this->short($start).' تا '.$this->short($end),
                'created' => 0,
                'done' => 0,
            ];
        }

        $place = function ($date, string $key) use (&$buckets, $from, $size, $count) {
            $i = intdiv((int) $from->diffInDays($date->startOfDay()), $size);
            $buckets[max(0, min($i, $count - 1))][$key]++;
        };

        $this->tasks()->where('created_at', '>=', $from)->limit(5000)->pluck('created_at')
            ->each(fn ($d) => $place($d, 'created'));

        $this->tasks()->inStatus(TaskStatus::Done)->where('completed_at', '>=', $from)->limit(5000)->pluck('completed_at')
            ->each(fn ($d) => $place($d, 'done'));

        return $buckets;
    }

    private function label(CarbonImmutable $d, int $size): string
    {
        if (config('orbit.calendar') === 'jalali') {
            $j = Jalalian::fromDateTime($d);

            return match ($size) {
                1 => (string) $j->getDay(),
                7 => $j->getMonth().'/'.$j->getDay(),
                default => $j->format('%B'),
            };
        }

        return match ($size) {
            1 => (string) $d->day,
            7 => $d->month.'/'.$d->day,
            default => $d->translatedFormat('M'),
        };
    }

    private function short(CarbonImmutable $d): string
    {
        return \App\Support\Dates::short($d);
    }

    /* ---------------- جدول‌ها ---------------- */

    /** پروژه‌ها با شمارنده‌ها؛ اول بیشترین عقب‌افتاده */
    public function projectRows(int $perPage = 8): LengthAwarePaginator
    {
        $today = today()->toDateString();

        $page = $this->projects()
            ->with('client:id,name')
            ->withCount([
                'tasks',
                'tasks as done_count' => fn ($q) => $q->where('status', 'done'),
                'tasks as overdue_count' => fn ($q) => $q
                    ->where('status', '!=', 'done')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', $today),
            ])
            ->orderByDesc('overdue_count')
            ->orderBy('name')
            ->paginate($perPage);

        $minutes = $this->minutesBy('project_id', $page->pluck('id')->all());

        $page->getCollection()->each(function (Project $p) use ($minutes) {
            $p->setAttribute('minutes', (int) ($minutes[$p->id] ?? 0));
        });

        return $page;
    }

    /** مجموع دقیقه‌های ثبت‌شده در بازه، به تفکیک پروژه یا کاربر @return array<int,int> */
    private function minutesBy(string $column, array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $rows = $this->time()
            ->when($column === 'project_id',
                fn ($q) => $q->join('tasks', 'tasks.id', '=', 'time_entries.task_id')
                    ->whereIn('tasks.project_id', $ids)
                    ->selectRaw('tasks.project_id as key_id, SUM(time_entries.minutes) as total')
                    ->groupBy('tasks.project_id'),
                fn ($q) => $q->whereIn('time_entries.user_id', $ids)
                    ->selectRaw('time_entries.user_id as key_id, SUM(time_entries.minutes) as total')
                    ->groupBy('time_entries.user_id'),
            )
            ->toBase()
            ->get();

        return $rows->pluck('total', 'key_id')->map(fn ($v) => (int) $v)->all();
    }

    /** حجم کار اعضای فعالِ پروژه‌های داخل محدوده */
    public function workload(): Collection
    {
        $ids = $this->projects()->pluck('projects.id')->all();
        $from = $this->from();
        $today = today()->toDateString();

        $users = User::query()
            ->active()
            ->whereHas('projects', fn ($q) => $q->whereIn('projects.id', $ids))
            ->withCount([
                'assignedTasks as open_count' => fn ($q) => $q->whereIn('project_id', $ids)->where('status', '!=', 'done'),
                'assignedTasks as overdue_count' => fn ($q) => $q
                    ->whereIn('project_id', $ids)
                    ->where('status', '!=', 'done')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', $today),
                'assignedTasks as done_count' => fn ($q) => $q
                    ->whereIn('project_id', $ids)
                    ->where('status', 'done')
                    ->where('completed_at', '>=', $from),
            ])
            ->orderByDesc('open_count')
            ->orderBy('name')
            ->limit(20)
            ->get();

        $minutes = $this->minutesBy('user_id', $users->pluck('id')->all());

        return $users->each(fn (User $u) => $u->setAttribute('minutes', (int) ($minutes[$u->id] ?? 0)));
    }

    /**
     * مدت زمان تکمیل تسک‌های انجام‌شده‌ی بازه (از ساخت تا تکمیل، به روز)
     *
     * @return array{buckets:array<int,array{label:string,count:int}>,total:int,median:?int}
     */
    public function completionTimes(): array
    {
        $days = $this->tasks()
            ->inStatus(TaskStatus::Done)
            ->where('completed_at', '>=', $this->from())
            ->limit(5000)
            ->get(['id', 'created_at', 'completed_at'])
            ->map(fn (Task $t) => max(0, (int) $t->created_at->startOfDay()->diffInDays($t->completed_at->startOfDay(), false)))
            ->sort()
            ->values();

        $defs = [
            ['همان روز', 0, 0],
            ['1 تا 3 روز', 1, 3],
            ['4 تا 7 روز', 4, 7],
            ['8 تا 14 روز', 8, 14],
            ['بیش از 14 روز', 15, PHP_INT_MAX],
        ];

        return [
            'buckets' => array_map(fn ($d) => [
                'label' => $d[0],
                'count' => $days->filter(fn ($n) => $n >= $d[1] && $n <= $d[2])->count(),
            ], $defs),
            'total' => $days->count(),
            'median' => $days->isEmpty() ? null : $days[intdiv($days->count(), 2)],
        ];
    }

    private function upcomingQuery(): Builder
    {
        return $this->tasks()
            ->open()
            ->whereBetween('due_date', [today()->toDateString(), today()->addDays(14)->toDateString()]);
    }

    /** تسک‌های باز با ددلاین در ۱۴ روز آینده (نزدیک‌ترین و مهم‌ترین اول) */
    public function upcoming(int $limit = 6): Collection
    {
        return $this->upcomingQuery()
            ->select(['id', 'project_id', 'assignee_id', 'title', 'status', 'priority', 'due_date'])
            ->with(['project:id,name,color', 'assignee:id,name,avatar_path'])
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->limit($limit)
            ->get();
    }

    public function upcomingCount(): int
    {
        return $this->upcomingQuery()->count();
    }

    public function unassignedOpen(): int
    {
        return $this->tasks()->open()->whereNull('assignee_id')->count();
    }

    /** قدیمی‌ترین تسک‌های عقب‌افتاده */
    public function overdueTasks(int $limit = 10): Collection
    {
        return $this->tasks()
            ->overdue()
            ->select(['id', 'project_id', 'assignee_id', 'title', 'due_date'])
            ->with(['project:id,name', 'assignee:id,name,avatar_path'])
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }
}
