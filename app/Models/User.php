<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'avatar_path',
        'is_active',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** نقش‌ها همیشه همراه کاربر لود می‌شوند (جلوگیری از lazy loading در strict mode) */
    protected $with = ['roles'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'notification_settings' => 'array',
        ];
    }

    /* ---------------------------------------------------------------
     | روابط
     |--------------------------------------------------------------- */

    /** پروژه‌هایی که کاربر عضوشان است */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'creator_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /* ---------------------------------------------------------------
     | اسکوپ‌ها
     |--------------------------------------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /* ---------------------------------------------------------------
     | کمکی‌ها
     |--------------------------------------------------------------- */

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin->value);
    }

    /** هر کاربر یک نقش دارد (UI فقط یک انتخاب می‌دهد) */
    public function roleName(): ?string
    {
        return $this->roles->first()?->name;
    }

    public function roleLabel(): string
    {
        return UserRole::labelFor($this->roleName());
    }

    /** آیا این کاربر تنها مدیر فعال سیستم است؟ (حفاظت از قفل‌شدن بیرون از سیستم) */
    public function isLastActiveAdmin(): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        return ! static::role(UserRole::Admin->value)
            ->where('is_active', true)
            ->whereKeyNot($this->getKey())
            ->exists();
    }

    public function isMemberOf(Project|int $project): bool
    {
        $id = $project instanceof Project ? $project->getKey() : $project;

        return $this->projects()->whereKey($id)->exists();
    }

    /** همه‌ی سشن‌های دیتابیسی این کاربر را می‌بندد (غیرفعال‌سازی، ریست رمز) */
    public function terminateSessions(): void
    {
        $this->forceFill(['remember_token' => null])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $this->getKey())->delete();
        }
    }

    /** حروف اول نام برای آواتار پیش‌فرض (حداکثر دو حرف) */
    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name)) ?: [];
        $letters = array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));

        return mb_strtoupper(implode('', $letters));
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? Storage::disk(config('orbit.avatars.disk'))->url($this->avatar_path)
            : null;
    }
}
