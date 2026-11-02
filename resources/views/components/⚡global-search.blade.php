<?php

use App\Models\Project;
use App\Models\Task;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $query = '';

    /** عبارت جستجو، بدون نویسه‌های خاص LIKE (% و _) و حداقل ۲ حرف */
    private function term(): string
    {
        $term = trim(str_replace(['%', '_', '\\'], '', $this->query));

        return mb_strlen($term) >= 2 ? $term : '';
    }

    #[Computed]
    public function projects()
    {
        $term = $this->term();

        if ($term === '') {
            return collect();
        }

        return Project::query()
            ->visibleTo(auth()->user())
            ->notArchived()
            ->whereLike('name', "%{$term}%")
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'color']);
    }

    #[Computed]
    public function tasks()
    {
        $term = $this->term();

        if ($term === '') {
            return collect();
        }

        return Task::query()
            ->visibleTo(auth()->user())
            ->whereLike('title', "%{$term}%")
            ->with('project:id,name')
            ->latest('updated_at')
            ->limit(8)
            ->get(['id', 'project_id', 'title', 'status']);
    }
};
?>

<div
    x-data="{
        open: false,
        active: 0,
        items() {
            return this.$refs.list ? [...this.$refs.list.querySelectorAll('[data-result]')] : [];
        },
        move(step) {
            const n = this.items().length;
            if (!n) return;
            this.active = (this.active + step + n) % n;
            this.$nextTick(() => this.items()[this.active]?.scrollIntoView({ block: 'nearest' }));
        },
        go() {
            this.items()[this.active]?.click();
        },
    }"
    x-init="$wire.$watch('query', () => active = 0)"
    @open-search.window="open = true; $nextTick(() => { $refs.input.focus(); $refs.input.select(); })"
    @keydown.escape.window="open = false"
    x-effect="document.body.classList.toggle('overflow-hidden', open)"
>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-[12vh]"
        role="dialog"
        aria-modal="true"
        aria-label="جستجو"
    >
        {{-- پس‌زمینه --}}
        <div
            x-show="open"
            x-transition.opacity
            @click="open = false"
            class="absolute inset-0 bg-black/40"
        ></div>

        {{-- پنل --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="panel relative flex max-h-[70vh] w-full max-w-xl flex-col overflow-hidden"
        >
            {{-- ورودی --}}
            <div class="flex shrink-0 items-center gap-3 border-b border-line px-4">
                <x-ui.icon name="search" class="size-5 text-muted" />

                <input
                    x-ref="input"
                    type="text"
                    wire:model.live.debounce.250ms="query"
                    @keydown.arrow-down.prevent="move(1)"
                    @keydown.arrow-up.prevent="move(-1)"
                    @keydown.enter.prevent="go()"
                    class="h-14 w-full bg-transparent text-[0.9375rem] text-fg placeholder:text-muted/70 focus:outline-none"
                    placeholder="جستجو در پروژه‌ها و تسک‌ها…"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="عبارت جستجو"
                />

                <svg wire:loading wire:target="query" class="size-4 shrink-0 animate-spin text-muted" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>

                <kbd dir="ltr" class="shrink-0 rounded border border-line bg-canvas px-1.5 py-0.5 font-sans text-[0.6875rem] leading-none text-muted">Esc</kbd>
            </div>

            {{-- نتایج --}}
            <div x-ref="list" class="min-h-0 flex-1 overflow-y-auto p-2">
                @php
                    $hasQuery = mb_strlen(trim(str_replace(['%', '_', '\\'], '', $query))) >= 2;
                    $i = 0;
                @endphp

                @if (! $hasQuery)
                    <p class="px-3 py-10 text-center text-sm text-muted">حداقل ۲ حرف بنویس.</p>
                @elseif ($this->projects->isEmpty() && $this->tasks->isEmpty())
                    <p class="px-3 py-10 text-center text-sm text-muted">نتیجه‌ای پیدا نشد.</p>
                @else
                    {{-- پروژه‌ها --}}
                    @if ($this->projects->isNotEmpty())
                        <p class="px-3 pb-1 pt-2 text-xs font-medium text-muted">پروژه‌ها</p>
                        <ul>
                            @foreach ($this->projects as $project)
                                <li wire:key="search-project-{{ $project->id }}">
                                    <a
                                        href="{{ route('projects.show', $project) }}"
                                        wire:navigate
                                        data-result
                                        @click="open = false"
                                        @mouseenter="active = {{ $i }}"
                                        :class="{ 'bg-primary-soft': active === {{ $i }} }"
                                        class="flex items-center gap-3 rounded-control px-3 py-2.5 text-sm transition"
                                    >
                                        <span
                                            class="flex size-6 shrink-0 items-center justify-center rounded-md text-xs font-bold text-white"
                                            style="background-color: {{ $project->color ?? '#5B5BD6' }}"
                                        >{{ mb_substr($project->name, 0, 1) }}</span>
                                        <span class="truncate font-medium">{{ $project->name }}</span>
                                    </a>
                                </li>
                                @php $i++; @endphp
                            @endforeach
                        </ul>
                    @endif

                    {{-- تسک‌ها --}}
                    @if ($this->tasks->isNotEmpty())
                        <p class="px-3 pb-1 pt-3 text-xs font-medium text-muted">تسک‌ها</p>
                        <ul>
                            @foreach ($this->tasks as $task)
                                <li wire:key="search-task-{{ $task->id }}">
                                    <a
{{--                                        href="{{ route('tasks.show', $task) }}"--}}
                                        href="#"
                                        wire:navigate
                                        data-result
                                        @click="open = false"
                                        @mouseenter="active = {{ $i }}"
                                        :class="{ 'bg-primary-soft': active === {{ $i }} }"
                                        class="flex items-center gap-3 rounded-control px-3 py-2.5 text-sm transition"
                                    >
                                        <span
                                            class="size-2.5 shrink-0 rounded-full"
                                            style="background-color: {{ $task->status->color() }}"
                                            title="{{ $task->status->label() }}"
                                        ></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate font-medium">{{ $task->title }}</span>
                                            <span class="block truncate text-xs text-muted">{{ $task->project->name }}</span>
                                        </span>
                                    </a>
                                </li>
                                @php $i++; @endphp
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>

            {{-- راهنمای کلیدها --}}
            <div class="flex shrink-0 items-center gap-4 border-t border-line bg-canvas px-4 py-2.5 text-xs text-muted max-sm:hidden">
                <span class="flex items-center gap-1.5"><kbd class="rounded border border-line bg-surface px-1.5 py-0.5 font-sans leading-none">↑</kbd><kbd class="rounded border border-line bg-surface px-1.5 py-0.5 font-sans leading-none">↓</kbd> انتخاب</span>
                <span class="flex items-center gap-1.5"><kbd class="rounded border border-line bg-surface px-1.5 py-0.5 font-sans leading-none">Enter</kbd> باز کردن</span>
            </div>
        </div>
    </div>
</div>
