<?php

namespace App\Models;

use App\Enums\Ability;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Task extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'project_id',
        'phase_id',
        'sprint_id',
        'creator_id',
        'assignee_id',
        'title',
        'description',
        'status',
        'priority',
        'type',
        'due_date',
        'estimate_minutes',
        'position',
        'completed_at',
        'deadline_reminded_for',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'type' => TaskType::class,
            'due_date' => 'date',
            'estimate_minutes' => 'integer',
            'position' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // تسک جدید ته ستونِ وضعیتش قرار می‌گیرد
        static::creating(function (Task $task) {
            if (! $task->position) {
                $status = $task->status?->value ?? TaskStatus::Todo->value;

                $max = static::withTrashed()
                    ->where('project_id', $task->project_id)
                    ->where('status', $status)
                    ->max('position');

                $task->position = ($max ?? 0) + (int) config('orbit.kanban.position_gap', 1000);
            }
        });

        // completed_at همیشه با وضعیت هماهنگ می‌ماند
        static::saving(function (Task $task) {
            if ($task->isDirty('status')) {
                $task->completed_at = $task->status === TaskStatus::Done
                    ? ($task->completed_at ?? now())
                    : null;
            }
        });
    }

    /* ---------------------------------------------------------------
     | روابط
     |--------------------------------------------------------------- */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->latest();
    }

    public function links(): MorphMany
    {
        return $this->morphMany(Link::class, 'linkable');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->latest('worked_on');
    }

    /* ---------------------------------------------------------------
     | اسکوپ‌ها
     |--------------------------------------------------------------- */

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', TaskStatus::Done->value);
    }

    public function scopeInStatus(Builder $query, TaskStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->where('assignee_id', $user->id);
    }

    /** تسک‌های باز که ددلاینشان گذشته */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_date')
            ->where('due_date', '<', today()->toDateString());
    }

    /** تسک‌های باز با ددلاین در $hours ساعت آینده (امروز تا فردا برای ۲۴ ساعت) */
    public function scopeDueWithin(Builder $query, int $hours): Builder
    {
        $days = (int) ceil($hours / 24);

        return $query->open()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [
                today()->toDateString(),
                today()->addDays($days)->toDateString(),
            ]);
    }

    /** با دسترسی «دیدن همه‌ی پروژه‌ها» همه را می‌بیند، وگرنه فقط تسک‌های پروژه‌های عضویت */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(Ability::ProjectsViewAll->value)) {
            return $query;
        }

        return $query->whereIn('project_id', $user->projects()->select('projects.id'));
    }

    /** شمارنده‌ی چک‌لیست و مجموع زمان ثبت‌شده برای کارت‌ها (بدون N+1) */
    public function scopeWithCardCounts(Builder $query): Builder
    {
        return $query
            ->withCount([
                'checklistItems',
                'checklistItems as checklist_done_count' => fn (Builder $q) => $q->where('is_done', true),
                'comments',
                'attachments',
            ])
            ->withSum('timeEntries as time_spent_minutes', 'minutes');
    }

    /* ---------------------------------------------------------------
     | محاسبه‌ها
     |--------------------------------------------------------------- */

    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn (): bool => $this->due_date !== null
            && $this->status !== TaskStatus::Done
            && $this->due_date->lt(today()));
    }

    /** مجموع دقیقه‌های ثبت‌شده؛ اگر با withCardCounts لود شده باشد کوئری نمی‌زند */
    public function spentMinutes(): int
    {
        $attrs = $this->getAttributes();

        return (int) ($attrs['time_spent_minutes'] ?? $this->timeEntries()->sum('minutes'));
    }

    /** @return array{done:int,total:int} */
    public function checklistProgress(): array
    {
        $attrs = $this->getAttributes();

        return [
            'done' => (int) ($attrs['checklist_done_count'] ?? $this->checklistItems()->where('is_done', true)->count()),
            'total' => (int) ($attrs['checklist_items_count'] ?? $this->checklistItems()->count()),
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('task')
            ->logOnly([
                'title', 'status', 'priority', 'type',
                'assignee_id', 'due_date', 'sprint_id', 'phase_id', 'estimate_minutes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
