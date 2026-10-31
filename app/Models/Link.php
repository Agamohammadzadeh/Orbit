<?php

namespace App\Models;

use App\Enums\LinkType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Link extends Model
{
    use HasFactory;

    protected $fillable = [
        'linkable_type',
        'linkable_id',
        'type',
        'title',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'type' => LinkType::class,
        ];
    }

    protected static function booted(): void
    {
        // اگر نوع مشخص نشده بود، از روی دامنه حدس زده می‌شود (Figma، GitHub، ...)
        static::saving(function (Link $link) {
            if ($link->type === null || $link->type === LinkType::Other) {
                $link->type = LinkType::detect($link->url);
            }
        });
    }

    /** Project یا Task */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function displayTitle(): string
    {
        return $this->title ?: (parse_url($this->url, PHP_URL_HOST) ?: $this->url);
    }
}
