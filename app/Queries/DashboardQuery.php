<?php

namespace App\Queries;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardQuery
{
    /** هفته از شنبه شروع می‌شود و جمعه تمام می‌شود */
    public static function weekStart(): CarbonInterface
    {
        return today()->startOfWeek(CarbonInterface::SATURDAY);
    }

    public static function weekEnd(): CarbonInterface
    {
        return self::weekStart()->addDays(6);
    }

    /** تسک‌های مسئولیت کاربر، در پروژه‌های آرشیو‌نشده */
    private function mine(User $user): Builder
    {
        return Task::query()
            ->assignedTo($user)
            ->whereHas('project', fn (Builder $q) => $q->notArchived());
    }

    /** @return array{open:int,overdue:int,today:int,week:int,done:int} */
    public function stats(User $user): array
    {
        $today = today()->toDateString();
        $weekEnd = self::weekEnd()->toDateString();

        return [
            'open' => $this->mine($user)->open()->count(),
            'overdue' => $this->mine($user)->overdue()->count(),
            'today' => $this->mine($user)->open()->where('due_date', $today)->count(),
            // «ددلاین این هفته» از امروز تا جمعه (شامل امروز)
            'week' => $this->mine($user)->open()->whereBetween('due_date', [$today, $weekEnd])->count(),
            'done' => $this->mine($user)
                ->inStatus(TaskStatus::Done)
                ->where('completed_at', '>=', self::weekStart())
                ->count(),
        ];
    }

    /**
     * @param  string  $filter  '' | overdue | week | done
     * @return Collection<int,Task>
     */
    public function myTasks(User $user, string $filter = ''): Collection
    {
        $query = $this->mine($user)
            ->select(['id', 'project_id', 'title', 'status', 'priority', 'type', 'due_date', 'completed_at'])
            ->with('project:id,name,slug,color');

        if ($filter === 'done') {
            return $query
                ->inStatus(TaskStatus::Done)
                ->where('completed_at', '>=', self::weekStart())
                ->orderByDesc('completed_at')
                ->limit(100)
                ->get();
        }

        $query->open();

        if ($filter === 'overdue') {
            $query->overdue();
        }

        if ($filter === 'week') {
            $query->whereBetween('due_date', [today()->toDateString(), self::weekEnd()->toDateString()]);
        }

        return $query
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->limit(200)
            ->get();
    }

    /** پروژه‌های فعالی که کاربر عضوشان است؛ اول پروژه‌هایی که تسک باز از او دارند */
    public function myProjects(User $user, int $limit = 5): Collection
    {
        return Project::query()
            ->whereHas('members', fn (Builder $q) => $q->whereKey($user->id))
            ->notArchived()
            ->withStatus(ProjectStatus::Active)
            ->withProgress()
            ->withCount([
                'tasks as my_open_count' => fn (Builder $q) => $q
                    ->where('assignee_id', $user->id)
                    ->where('status', '!=', TaskStatus::Done->value),
            ])
            ->with(['sprints' => fn ($q) => $q->active()])
            ->orderByDesc('my_open_count')
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * مجموع دقیقه‌های ثبت‌شده‌ی هفت روز هفته (شنبه تا جمعه)
     *
     * @return array<int,array{date:CarbonInterface,minutes:int}>
     */
    public function weekTime(User $user): array
    {
        $start = self::weekStart();

        $totals = DB::table('time_entries')
            ->where('user_id', $user->id)
            ->whereBetween('worked_on', [$start->toDateString(), self::weekEnd()->toDateString()])
            ->selectRaw('worked_on, SUM(minutes) as total')
            ->groupBy('worked_on')
            ->pluck('total', 'worked_on');

        return collect(range(0, 6))
            ->map(function (int $i) use ($start, $totals) {
                $date = $start->addDays($i);

                return ['date' => $date, 'minutes' => (int) ($totals[$date->toDateString()] ?? 0)];
            })
            ->all();
    }

    /* ---------------- نمای تیم ---------------- */

    private function allTasks(): Builder
    {
        return Task::query()->whereHas('project', fn (Builder $q) => $q->notArchived());
    }

    /** @return array{open:int,overdue:int,unassigned:int,done:int} */
    public function teamStats(): array
    {
        return [
            'open' => $this->allTasks()->open()->count(),
            'overdue' => $this->allTasks()->overdue()->count(),
            'unassigned' => $this->allTasks()->open()->whereNull('assignee_id')->count(),
            'done' => $this->allTasks()
                ->inStatus(TaskStatus::Done)
                ->where('completed_at', '>=', self::weekStart())
                ->count(),
        ];
    }

    /** حجم کار اعضای فعال: تعداد تسک باز و عقب‌افتاده */
    public function teamWorkload(int $limit = 8): Collection
    {
        $inActiveProject = fn (Builder $q) => $q->whereHas('project', fn (Builder $p) => $p->notArchived());

        return User::query()
            ->active()
            ->withCount([
                'assignedTasks as open_count' => fn (Builder $q) => $inActiveProject($q->open()),
                'assignedTasks as overdue_count' => fn (Builder $q) => $inActiveProject($q->overdue()),
            ])
            ->orderByDesc('open_count')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /** قدیمی‌ترین تسک‌های عقب‌افتاده‌ی همه‌ی پروژه‌ها */
    public function teamOverdue(int $limit = 6): Collection
    {
        return $this->allTasks()
            ->overdue()
            ->select(['id', 'project_id', 'assignee_id', 'title', 'due_date'])
            ->with(['project:id,name', 'assignee:id,name,avatar_path'])
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }
}
