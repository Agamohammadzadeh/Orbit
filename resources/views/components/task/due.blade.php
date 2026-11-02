@props(['task'])

@php
    $done = $task->status->isDone();
    $diff = \App\Support\Dates::daysFromToday($task->due_date);

    $label = match (true) {
        $diff === null => null,
        $diff === 0 => 'امروز',
        $diff === 1 => 'فردا',
        $diff === -1 => 'دیروز',
        $diff < 0 => abs($diff).' روز پیش',
        default => $diff.' روز دیگر',
    };

    $tone = match (true) {
        $done => 'text-muted',
        $diff !== null && $diff < 0 => 'bg-danger/10 text-danger',
        $diff === 0 => 'bg-primary-soft text-primary',
        default => 'text-muted',
    };
@endphp

@if ($label)
    <span
        title="{{ \App\Support\Dates::short($task->due_date) }}"
        {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium', $tone]) }}
    >
        <x-ui.icon name="calendar" class="size-3.5" />
        {{ $label }}
    </span>
@endif
