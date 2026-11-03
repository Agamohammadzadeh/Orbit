<?php

use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('پروژه')] class extends Component {
    public Project $project;

    /** board | list | sprints */
    #[Url]
    public string $tab = 'board';

    /* لینک‌های پروژه */
    public bool $showLinkForm = false;

    public string $linkUrl = '';

    public string $linkTitle = '';

    public string $linkType = 'auto';

    public function mount(Project $project): void
    {
        Gate::authorize('view', $project);

        $this->project = $project;

        if (! in_array($this->tab, ['board', 'list', 'sprints', 'phases'], true)) {
            $this->tab = 'board';
        }
    }

    #[Computed]
    public function stats(): array
    {
        $total = $this->project->tasks()->count();
        $done = $this->project->tasks()->where('status', 'done')->count();

        return [
            'total' => $total,
            'done' => $done,
            'open' => $total - $done,
            'overdue' => $this->project->tasks()->overdue()->count(),
            'progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }

    #[Computed]
    public function members()
    {
        return $this->project->members()->orderBy('name')->get(['users.id', 'users.name', 'users.avatar_path']);
    }

    #[Computed]
    public function links()
    {
        return $this->project->links()->get();
    }

    #[On('task-saved')]
    #[On('task-updated')]
    public function refreshStats(): void
    {
        unset($this->stats);
    }

    /* ---------------- لینک‌ها ---------------- */

    public function openLinkForm(): void
    {
        Gate::authorize('update', $this->project);

        $this->reset(['linkUrl', 'linkTitle']);
        $this->linkType = 'auto';
        $this->resetValidation();
        $this->showLinkForm = true;
    }

    public function closeLinkForm(): void
    {
        $this->showLinkForm = false;
        $this->resetValidation();
    }

    public function saveLink(): void
    {
        Gate::authorize('update', $this->project);

        $this->validate(
            [
                'linkUrl' => ['required', 'url:http,https', 'max:2048'],
                'linkTitle' => ['nullable', 'string', 'max:80'],
                'linkType' => ['required', 'in:auto,figma,repo,staging,other'],
            ],
            [
                'linkUrl.required' => 'آدرس لینک را وارد کن.',
                'linkUrl.url' => 'آدرس باید با http:// یا https:// شروع شود.',
                'linkUrl.max' => 'آدرس خیلی طولانی است.',
                'linkTitle.max' => 'عنوان نباید بیشتر از :max حرف باشد.',
            ],
        );

        // «auto»: نوع از روی دامنه تشخیص داده می‌شود (Figma، GitHub و...)
        $this->project->links()->create([
            'type' => $this->linkType === 'auto' ? null : $this->linkType,
            'title' => trim($this->linkTitle) !== '' ? trim($this->linkTitle) : null,
            'url' => trim($this->linkUrl),
        ]);

        unset($this->links);
        $this->closeLinkForm();
    }

    public function deleteLink(int $id): void
    {
        Gate::authorize('update', $this->project);

        $this->project->links()->whereKey($id)->delete();

        unset($this->links);
    }
};
?>

@php
    $project = $this->project;
    $stats = $this->stats;
    $me = auth()->user();
    $canEdit = $me->can('update', $project);
    $canTask = $me->can('create', [\App\Models\Task::class, $project]) && ! $project->isArchived();
    $days = \App\Support\Dates::daysFromToday($project->end_date);
    $live = $project->status !== \App\Enums\ProjectStatus::Done && ! $project->isArchived();

    $linkIcons = ['figma' => 'layout', 'repo' => 'git-branch', 'staging' => 'globe', 'other' => 'link'];
    $tabs = [
        'board' => ['برد', 'columns'],
        'list' => ['لیست', 'list'],
        'sprints' => ['اسپرینت‌ها', 'zap'],
        'phases' => ['مراحل', 'layers'],
    ];

    $linkTypeOptions = ['auto' => 'تشخیص خودکار'];
    foreach (\App\Enums\LinkType::cases() as $t) {
        $linkTypeOptions[$t->value] = $t->label();
    }
@endphp

<div class="space-y-6">
    {{-- سربرگ --}}
    <div class="space-y-4">
        <a href="{{ route('projects.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-muted transition hover:text-primary">
            <x-ui.icon name="arrow-left" class="size-4 rtl:rotate-180" />
            پروژه‌ها
        </a>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-4">
                <span
                    class="flex size-12 shrink-0 items-center justify-center rounded-xl text-xl font-bold text-white"
                    style="background-color: {{ $project->color ?? '#5B5BD6' }}"
                >{{ mb_substr($project->name, 0, 1) }}</span>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-2xl font-bold tracking-tight">{{ $project->name }}</h1>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium" style="color: {{ $project->status->color() }}; background-color: {{ $project->status->color() }}1A">
                            <span class="size-1.5 rounded-full" style="background-color: {{ $project->status->color() }}"></span>
                            {{ $project->status->label() }}
                        </span>
                        @if ($project->isArchived())
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-line/60 px-2.5 py-0.5 text-xs font-medium text-muted">
                                <x-ui.icon name="archive" class="size-3.5" />
                                آرشیو شده
                            </span>
                        @endif
                    </div>

                    <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
                        <span>{{ $project->client?->name ?? 'بدون مشتری' }}</span>
                        @if ($project->end_date)
                            <span class="inline-flex items-center gap-1 {{ $live && $days < 0 ? 'font-medium text-danger' : '' }}">
                                <x-ui.icon name="calendar" class="size-3.5" />
                                پایان: {{ \App\Support\Dates::short($project->end_date) }}
                                @if ($live)
                                    ({{ $days < 0 ? abs($days).' روز دیرکرد' : ($days === 0 ? 'امروز' : $days.' روز مانده') }})
                                @endif
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                {{-- اعضا --}}
                <div class="flex -space-x-2 rtl:space-x-reverse" aria-label="اعضای پروژه">
                    @foreach ($this->members->take(5) as $m)
                        <x-ui.avatar :user="$m" :size="32" class="ring-2 ring-canvas" :title="$m->name" />
                    @endforeach
                    @if ($this->members->count() > 5)
                        <span class="tabular flex size-8 items-center justify-center rounded-full bg-line text-xs font-medium text-muted ring-2 ring-canvas">+{{ $this->members->count() - 5 }}</span>
                    @endif
                </div>

                @if ($canEdit)
                    <a href="{{ route('projects.edit', $project) }}" wire:navigate class="btn-secondary">
                        <x-ui.icon name="edit" class="size-4.5" />
                        ویرایش
                    </a>
                @endif

                @if ($canTask)
                    <button type="button" wire:click="$dispatch('open-task-form', { projectId: {{ $project->id }} })" class="btn-primary">
                        <x-ui.icon name="plus" class="size-4.5" />
                        تسک جدید
                    </button>
                @endif
            </div>
        </div>

        @if ($project->description)
            <p class="max-w-3xl text-sm leading-relaxed text-muted">{{ $project->description }}</p>
        @endif

        {{-- پیشرفت --}}
        <div class="card flex flex-wrap items-center gap-x-8 gap-y-4 p-4">
            <div class="min-w-48 flex-1">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium">پیشرفت پروژه</span>
                    <span class="tabular text-muted">{{ $stats['progress'] }}%</span>
                </div>
                <x-ui.progress :value="$stats['progress']" :tone="$stats['progress'] >= 100 ? 'success' : 'primary'" class="mt-2" />
            </div>

            <dl class="flex items-center gap-6 text-sm">
                <div><dt class="text-xs text-muted">کل</dt><dd class="tabular font-bold">{{ $stats['total'] }}</dd></div>
                <div><dt class="text-xs text-muted">باز</dt><dd class="tabular font-bold">{{ $stats['open'] }}</dd></div>
                <div><dt class="text-xs text-muted">انجام‌شده</dt><dd class="tabular font-bold text-success">{{ $stats['done'] }}</dd></div>
                <div><dt class="text-xs text-muted">عقب‌افتاده</dt><dd class="tabular font-bold {{ $stats['overdue'] > 0 ? 'text-danger' : '' }}">{{ $stats['overdue'] }}</dd></div>
            </dl>
        </div>

        {{-- لینک‌ها (Figma، ریپو، staging) --}}
        @if ($this->links->isNotEmpty() || $canEdit)
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($this->links as $l)
                    <span wire:key="link-{{ $l->id }}" class="inline-flex h-8 items-center rounded-full border border-line bg-surface ps-3 {{ $canEdit ? 'pe-1' : 'pe-3' }} text-sm">
                        <a href="{{ $l->url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 transition hover:text-primary">
                            <x-ui.icon :name="$linkIcons[$l->type->value] ?? 'link'" class="size-4 text-muted" />
                            {{ $l->displayTitle() }}
                            <x-ui.icon name="external-link" class="size-3.5 text-muted" />
                        </a>

                        @if ($canEdit)
                            <button
                                type="button"
                                @click="$store.confirm.ask({
                                    title: 'حذف لینک',
                                    message: @js('لینک «'.$l->displayTitle().'» از پروژه حذف شود؟'),
                                    confirmLabel: 'حذف',
                                    tone: 'danger',
                                    action: () => $wire.deleteLink({{ $l->id }}),
                                })"
                                class="ms-1 flex size-6 items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger"
                                aria-label="حذف لینک"
                            >
                                <x-ui.icon name="x" class="size-3.5" />
                            </button>
                        @endif
                    </span>
                @endforeach

                @if ($canEdit)
                    <button type="button" wire:click="openLinkForm" class="inline-flex h-8 items-center gap-1.5 rounded-full border border-dashed border-line px-3 text-sm text-muted transition hover:border-primary/40 hover:text-primary">
                        <x-ui.icon name="plus" class="size-3.5" />
                        لینک
                    </button>
                @endif
            </div>
        @endif
    </div>

    @if ($project->isArchived())
        <div class="flex items-center gap-2.5 rounded-control border border-line bg-surface px-4 py-3 text-sm text-muted">
            <x-ui.icon name="archive" class="size-4.5" />
            این پروژه آرشیو شده و تسک‌هایش فقط‌خواندنی است.
        </div>
    @endif

    {{-- تب‌ها --}}
    <div class="flex gap-1 overflow-x-auto border-b border-line" role="tablist">
        @foreach ($tabs as $key => [$text, $icon])
            <button
                type="button"
                role="tab"
                wire:click="$set('tab', '{{ $key }}')"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                @class([
                    '-mb-px flex h-11 shrink-0 items-center gap-2 border-b-2 px-4 text-sm font-medium transition',
                    'border-primary text-primary' => $tab === $key,
                    'border-transparent text-muted hover:text-fg' => $tab !== $key,
                ])
            >
                <x-ui.icon :name="$icon" class="size-4.5" />
                {{ $text }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'list')
        <livewire:project.task-list :project="$project" />
    @elseif ($tab === 'sprints')
        <livewire:project.sprints :project="$project" />
    @elseif ($tab === 'phases')
        <livewire:project.phases :project="$project" />
    @else
        <livewire:project.board :project="$project" />
    @endif

    {{-- مودال لینک --}}
    @if ($showLinkForm)
        <form wire:submit="saveLink" novalidate>
            <x-ui.modal title="افزودن لینک" description="Figma، ریپازیتوری، سایت staging یا هر لینک مرجع دیگر." close="closeLinkForm" size="max-w-md">
                <div class="space-y-5">
                    <div class="space-y-1.5">
                        <label for="link-url" class="text-sm font-medium">آدرس</label>
                        <input
                            id="link-url"
                            type="url"
                            wire:model="linkUrl"
                            x-init="$nextTick(() => $el.focus())"
                            dir="ltr"
                            class="input text-start"
                            placeholder="https://www.figma.com/file/…"
                            autocomplete="off"
                            aria-invalid="{{ $errors->has('linkUrl') ? 'true' : 'false' }}"
                        />
                        @error('linkUrl')
                        <p class="text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="link-title" class="text-sm font-medium">عنوان <span class="font-normal text-muted">(اختیاری)</span></label>
                        <input id="link-title" type="text" wire:model="linkTitle" class="input" placeholder="مثلاً: طرح نهایی صفحه‌ی اصلی" autocomplete="off" />
                        @error('linkTitle')
                        <p class="text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="link-type" class="text-sm font-medium">نوع</label>
                        <x-ui.select id="link-type" wire:model="linkType" :options="$linkTypeOptions" />
                    </div>
                </div>

                <x-slot:footer>
                    <button type="button" wire:click="closeLinkForm" class="btn-secondary">انصراف</button>
                    <button type="submit" class="btn-primary">افزودن</button>
                </x-slot:footer>
            </x-ui.modal>
        </form>
    @endif
</div>
