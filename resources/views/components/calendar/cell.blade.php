@props(['cell', 'tasks' => [], 'deadlines' => [], 'max' => 3])

@php
    $tasks = collect($tasks);
    $deadlines = collect($deadlines);
    $total = $tasks->count() + $deadlines->count();

    // اول پایان پروژه‌ها، بعد تسک‌ها، تا سقف max
    $room = max(0, $max - $deadlines->count());
    $shownTasks = $tasks->take($room);
    $hidden = $total - $deadlines->count() - $shownTasks->count();

    $overdueCount = $tasks->filter(fn ($t) => ! $t->status->isDone() && $t->due_date->lt(today()))->count();
@endphp

<div
    wire:click="openDay('{{ $cell['key'] }}')"
    @class([
        'min-h-20 cursor-pointer border-b border-e border-line p-1.5 transition hover:bg-line/40 md:min-h-32',
        'bg-canvas/60' => ! $cell['inMonth'],
        'bg-line/20' => $cell['inMonth'] && $cell['weekend'],
    ])
>
    <div class="flex items-center justify-between">
        <span @class([
            'tabular flex size-7 items-center justify-center rounded-full text-sm',
            'bg-primary font-bold text-on-primary' => $cell['today'],
            'text-muted' => ! $cell['today'] && ! $cell['inMonth'],
            'font-medium' => ! $cell['today'] && $cell['inMonth'],
        ])>{{ $cell['label'] }}</span>

        @if ($overdueCount > 0)
            <span class="tabular hidden rounded-full bg-danger/10 px-1.5 text-[0.6875rem] font-medium text-danger md:inline" title="عقب‌افتاده">{{ $overdueCount }}</span>
        @endif
    </div>

    {{-- موبایل: فقط شمارنده --}}
    @if ($total > 0)
        <div class="mt-1.5 flex items-center gap-1 md:hidden">
            <span class="size-2 rounded-full {{ $overdueCount > 0 ? 'bg-danger' : 'bg-primary' }}"></span>
            <span class="tabular text-xs text-muted">{{ $total }}</span>
        </div>
    @endif

    {{-- دسکتاپ: چیپ‌ها --}}
    @if ($total > 0)
        <ul class="mt-1 hidden space-y-1 md:block">
            @foreach ($deadlines as $p)
                <li wire:key="deadline-{{ $cell['key'] }}-{{ $p->id }}">
                    <a
                        href="{{ route('projects.show', $p) }}"
                        wire:navigate
                        @click.stop
                        title="پایان پروژه: {{ $p->name }}"
                        class="flex items-center gap-1.5 truncate rounded bg-primary-soft px-1.5 py-1 text-xs font-medium text-primary transition hover:bg-primary/20"
                    >
                        <x-ui.icon name="flag" class="size-3 shrink-0" />
                        <span class="truncate">پایان: {{ $p->name }}</span>
                    </a>
                </li>
            @endforeach

            @foreach ($shownTasks as $t)
                @php
                    $done = $t->status->isDone();
                    $late = ! $done && $t->due_date->lt(today());
                @endphp
                <li wire:key="task-{{ $cell['key'] }}-{{ $t->id }}">
                    <a
                        href="{{ route('tasks.show', $t) }}"
                        wire:navigate
                        @click.stop
                        title="{{ $t->title }} — {{ $t->project->name }}"
                        style="border-inline-start: 3px solid {{ $t->project->color ?? '#5B5BD6' }}"
                        @class([
                            'block truncate rounded-s-none rounded-e bg-surface px-1.5 py-1 text-xs shadow-card transition hover:bg-line/60',
                            'text-muted line-through' => $done,
                            'font-medium text-danger' => $late,
                        ])
                    >{{ $t->title }}</a>
                </li>
            @endforeach

            @if ($hidden > 0)
                <li class="px-1.5 text-xs text-muted">+<span class="tabular">{{ $hidden }}</span> مورد دیگر</li>
            @endif
        </ul>
    @endif
</div>
