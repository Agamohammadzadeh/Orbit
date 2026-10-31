<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'task_id',
        'user_id',
        'body',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * شناسه‌ی کاربران منشن‌شده؛ توکن‌ها به شکل @[user:12] در متن ذخیره می‌شوند.
     *
     * @return array<int,int>
     */
    public function mentionedUserIds(): array
    {
        preg_match_all(config('orbit.mentions.pattern'), (string) $this->body, $matches);

        $ids = array_values(array_unique(array_map('intval', $matches[1] ?? [])));

        return array_slice($ids, 0, (int) config('orbit.mentions.max_per_comment', 10));
    }
}
