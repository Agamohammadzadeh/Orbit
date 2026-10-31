<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /** ۶ پروژه‌ی فعالی که کاربر می‌بیند، به ترتیب آخرین تغییر */
    #[Computed]
    public function projects()
    {
        return Project::query()
            ->visibleTo(auth()->user())
            ->notArchived()
            ->withStatus(ProjectStatus::Active)
            ->latest('updated_at')
            ->limit(6)
            ->get(['id', 'name', 'slug', 'color']);
    }
};
?>

<div>
    @if ($this->projects->isNotEmpty())
        @php $current = request()->route('project'); @endphp

        <div class="mb-2 flex items-center justify-between px-3 lg:sb-collapsed:hidden">
            <p class="text-xs font-medium text-muted">پروژه‌های اخیر</p>
            <a
{{--                href="{{ route('projects.index') }}" --}}
                href="#"
                wire:navigate
                class="text-xs text-muted transition hover:text-primary"
            >همه</a>
        </div>
        <div class="mx-2 mb-2 hidden h-px bg-line lg:sb-collapsed:block"></div>

        <ul class="space-y-1">
            @foreach ($this->projects as $project)
                @php $active = $current instanceof \App\Models\Project && $current->id === $project->id; @endphp
                <li wire:key="sidebar-project-{{ $project->id }}">
                    <a
                        href="#"
{{--                        href="{{ route('projects.show', $project) }}"--}}
                        wire:navigate
                        title="{{ $project->name }}"
                        @if ($active) aria-current="page" @endif
                        @class([
                            'flex h-10 items-center gap-3 rounded-control px-3 text-sm font-medium transition lg:sb-collapsed:justify-center lg:sb-collapsed:px-0',
                            'bg-primary-soft text-primary' => $active,
                            'text-muted hover:bg-line/50 hover:text-fg' => ! $active,
                        ])
                    >
                        <span
                            class="flex size-5 shrink-0 items-center justify-center rounded-md text-[0.6875rem] font-bold text-white"
                            style="background-color: {{ $project->color ?? '#5B5BD6' }}"
                        >{{ mb_substr($project->name, 0, 1) }}</span>
                        <span class="truncate lg:sb-collapsed:hidden">{{ $project->name }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
