<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// Deadline reminders
Schedule::command('orbit:send-deadline-reminders')
    ->dailyAt(config('orbit.reminders.time', '09:00'))
    ->withoutOverlapping();
