<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'minutes',
        'worked_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'worked_on' => 'date',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** ساعت با دو رقم اعشار (۹۰ دقیقه = ۱.۵) */
    protected function hours(): Attribute
    {
        return Attribute::get(fn (): float => round($this->minutes / 60, 2));
    }
}
