@props(['task', 'editable' => false])

@php
    $status = $task->status;
    $done = $status->isDone();
    $due = $task->due_date;
    $diff = $due ? (int) today()->diffInDays($due, false) : null;

    $dueLabel = match (true) {
        $done && $task->completed_at !== null => 'انجام شد '.$task->completed_at->diffForHumans(),
        $diff === null => null,
        $diff === 0 => 'امروز',
        $diff === 1 => 'فردا',
        $diff === -1 => 'دیروز',
        $diff < 0 => abs($diff).' روز پیش',
        default => $diff.' روز دیگر',
    };

    $dueTone = match (true) {
        $done => 'text-muted',
        $diff !== null && $diff < 0 => 'text-danger',
        $diff === 0 => 'text-primary',
        default => 'text-muted',
    };

    $dueTitle = $due
        ? (config('orbit.calendar') === 'jalali'
            ? \Morilog\Jalali\Jalalian::fromDateTime($due)->format('Y/m/d')
            : $due->format('Y/m/d'))
        : '';

    $hot = in_array($task->priority, [\App\Enums\TaskPriority::High, \App\Enums\TaskPriority::Urgent], true);
@endphp

<div {{ $attributes->class('flex items-center gap-3 px-4 py-3 transition hover:bg-line/30') }}>
    {{-- وضعیت (قابل تغییر سریع) --}}
    @if ($editable)
        <x-ui.dropdown align="start" width="w-52" class="shrink-0">
            <x-slot:trigger>
                <button
                    type="button"
                    @click="toggle()"
                    :aria-expanded="open"
                    aria-haspopup="menu"
                    class="flex size-8 items-center justify-center rounded-full transition hover:bg-line/60"
                    title="تغییر وضعیت: {{ $status->label() }}"
                >
                    <x-task.status-icon :status="$status" />
                </button>
            </x-slot:trigger>

            @foreach (\App\Enums\TaskStatus::cases() as $s)
                <x-ui.dropdown-item wire:click="setStatus({{ $task->id }}, '{{ $s->value }}')">
                    <x-task.status-icon :status="$s" class="size-4.5" />
                    {{ $s->label() }}
                    @if ($s === $status)
                        <x-ui.icon name="check" class="ms-auto size-4 text-primary" />
                    @endif
                </x-ui.dropdown-item>
            @endforeach
        </x-ui.dropdown>
    @else
        <span class="flex size-8 shrink-0 items-center justify-center">
            <x-task.status-icon :status="$status" />
        </span>
    @endif

    {{-- عنوان و پروژه --}}
    <div class="min-w-0 flex-1">
        <a
{{--            href="{{ route('tasks.show', $task) }}"--}}
            href="#"
            wire:navigate
            @class([
                'block truncate text-sm font-medium transition hover:text-primary',
                'text-muted line-through' => $done,
            ])
        >{{ $task->title }}</a>

        <div class="mt-0.5 flex min-w-0 items-center gap-2 text-xs text-muted">
            <span class="flex min-w-0 items-center gap-1.5">
                <span class="size-2 shrink-0 rounded-[3px]" style="background-color: {{ $task->project->color ?? '#5B5BD6' }}"></span>
                <span class="truncate">{{ $task->project->name }}</span>
            </span>
            <span aria-hidden="true">·</span>
            <span class="shrink-0" style="color: {{ $task->type->color() }}">{{ $task->type->label() }}</span>
        </div>
    </div>

    {{-- اولویت بالا --}}
    @if ($hot && ! $done)
        <span class="shrink-0" style="color: {{ $task->priority->color() }}" title="اولویت {{ $task->priority->label() }}">
            <x-ui.icon name="flag" class="size-4" />
        </span>
    @endif

    {{-- ددلاین --}}
    @if ($dueLabel)
        <span title="{{ $dueTitle }}" class="shrink-0 text-xs font-medium {{ $dueTone }}">{{ $dueLabel }}</span>
    @endif
</div>
