<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Morilog\Jalali\Jalalian;

class Dates
{
    /** تاریخ کوتاه (شمسی یا میلادی طبق config('orbit.calendar')) */
    public static function short(?CarbonInterface $date): string
    {
        if (! $date) {
            return '—';
        }

        return config('orbit.calendar') === 'jalali'
            ? Jalalian::fromDateTime($date)->format('Y/m/d')
            : $date->format('Y/m/d');
    }

    /** اختلاف روز تا امروز (منفی = گذشته، null = تاریخ ندارد) */
    public static function daysFromToday(?CarbonInterface $date): ?int
    {
        return $date ? (int) today()->diffInDays($date->startOfDay(), false) : null;
    }
}
