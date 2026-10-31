<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Project extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id',
        'owner_id',
        'name',
        'slug',
        'description',
        'status',
        'color',
        'start_date',
        'end_date',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // اسلاگ خودکار؛ اگر نام فارسی بود و اسلاگ خالی شد، یک شناسه‌ی تصادفی می‌گیرد
        static::creating(function (Project $project) {
            if (blank($project->slug)) {
                $project->slug = static::makeUniqueSlug($project->name);
            }
        });
    }

    public static function makeUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    /** آدرس‌ها با slug ساخته می‌شوند: /projects/{project:slug} */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /* ---------------------------------------------------------------
     | روابط
     |--------------------------------------------------------------- */

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class)->orderBy('position');
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class)->orderByDesc('starts_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function links(): MorphMany
    {
        return $this->morphMany(Link::class, 'linkable');
    }

    /* ---------------------------------------------------------------
     | اسکوپ‌ها
     |--------------------------------------------------------------- */

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeWithStatus(Builder $query, ProjectStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /** مدیر همه‌ی پروژه‌ها را می‌بیند، عضو فقط پروژه‌های خودش را */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('members', fn (Builder $q) => $q->whereKey($user->id));
    }

    /** شمارنده‌ها برای محاسبه‌ی درصد پیشرفت بدون N+1 */
    public function scopeWithProgress(Builder $query): Builder
    {
        return $query->withCount([
            'tasks',
            'tasks as done_tasks_count' => fn (Builder $q) => $q->where('status', 'done'),
        ]);
    }

    /* ---------------------------------------------------------------
     | محاسبه‌ها
     |--------------------------------------------------------------- */

    /** درصد پیشرفت (۰ تا ۱۰۰). برای لیست‌ها از scopeWithProgress استفاده کن. */
    protected function progress(): Attribute
    {
        return Attribute::get(function (): int {
            $attrs = $this->getAttributes();

            $total = $attrs['tasks_count'] ?? $this->tasks()->count();
            $done = $attrs['done_tasks_count'] ?? $this->tasks()->where('status', 'done')->count();

            return $total > 0 ? (int) round($done / $total * 100) : 0;
        });
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('project')
            ->logOnly(['name', 'status', 'client_id', 'start_date', 'end_date', 'archived_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
