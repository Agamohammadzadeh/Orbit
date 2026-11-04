<?php

use App\Queries\DashboardQuery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function projects()
    {
        return (new DashboardQuery)->myProjects(auth()->user(), 5);
    }

    /** با تغییر وضعیت تسک‌ها، درصد پیشرفت دوباره محاسبه می‌شود */
    #[On('task-updated')]
    public function refresh(): void
    {
        unset($this->projects);
    }
};
?>

<div class="card">
    <div class="flex items-center justify-between border-b border-line px-5 py-4">
        <h2 class="text-base font-bold">پروژه‌های من</h2>
        <a
            href="{{ route('projects.index') }}"
            wire:navigate
            class="text-sm text-muted transition hover:text-primary"
        >
            همه
        </a>
    </div>

    @if ($this->projects->isEmpty())
        <x-ui.empty-state title="پروژه‌ای نیست" icon="folder">
            هنوز عضو پروژه‌ی فعالی نیستی.
        </x-ui.empty-state>
    @else
        <ul class="divide-y divide-line">
            @foreach ($this->projects as $project)
                @php
                    $sprint = $project->sprints->first();
                    $left = $sprint ? (int) today()->diffInDays($sprint->ends_at, false) : null;
                    $progress = $project->progress;
                @endphp

                <li wire:key="project-{{ $project->id }}">
                    <a
                        href="{{ route('projects.show', $project) }}"
                        wire:navigate
                        class="block px-5 py-4 transition hover:bg-line/30"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg text-sm font-bold text-white"
                                style="background-color: {{ $project->color ?? '#5B5BD6' }}"
                            >{{ mb_substr($project->name, 0, 1) }}</span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="truncate text-sm font-medium">{{ $project->name }}</span>
                                    <span class="tabular shrink-0 text-xs text-muted">{{ $progress }}%</span>
                                </div>
                                <x-ui.progress :value="$progress" :tone="$progress >= 100 ? 'success' : 'primary'" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                            <span>
                                <span class="tabular font-medium text-fg">{{ $project->my_open_count }}</span>
                                تسک باز از من
                            </span>

                            @if ($sprint)
                                <span class="inline-flex items-center gap-1">
                                    <x-ui.icon name="clock" class="size-3.5" />
                                    {{ $sprint->name }}
                                    ·
                                    {{ $left > 0 ? $left.' روز مانده' : ($left === 0 ? 'امروز تمام می‌شود' : 'مهلت گذشته') }}
                                </span>
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
