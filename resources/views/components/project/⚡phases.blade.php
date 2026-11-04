<?php

use App\Actions\Phases\DeletePhase;
use App\Actions\Phases\ReorderPhases;
use App\Actions\Phases\SavePhase;
use App\Actions\Projects\ApplyPhaseTemplate;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Project $project;

    /* افزودن سریع */
    public string $newName = '';

    /* تغییر نام (مودال) */
    public bool $showForm = false;

    public ?int $phaseId = null;

    public string $name = '';

    /* قالب */
    public string $template = 'custom_website';

    public ?string $flash = null;

    public bool $flashIsError = false;

    public int $flashId = 0;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    /* ---------------- داده‌ها ---------------- */

    #[Computed]
    public function phases()
    {
        return $this->project->phases()
            ->withCount(['tasks', 'tasks as done_tasks_count' => fn ($q) => $q->where('status', 'done')])
            ->get();
    }

    /** تسک‌های بازی که به هیچ مرحله‌ای وصل نیستند */
    #[Computed]
    public function unphased(): int
    {
        return Task::query()->where('project_id', $this->project->id)->whereNull('phase_id')->open()->count();
    }

    #[On('task-saved')]
    public function refresh(): void
    {
        unset($this->phases, $this->unphased);
    }

    private function notify(string $message, bool $error = false): void
    {
        $this->flash = $message;
        $this->flashIsError = $error;
        $this->flashId++;
    }

    private function authorizeManage(): void
    {
        Gate::authorize('update', $this->project);
        abort_if($this->project->isArchived(), 403);
    }

    private function phase(int $id): Phase
    {
        return Phase::query()->where('project_id', $this->project->id)->findOrFail($id);
    }

    /* ---------------- افزودن، تغییر نام ---------------- */

    public function add(): void
    {
        $this->authorizeManage();

        $this->validate(
            ['newName' => ['required', 'string', 'max:80']],
            ['newName.required' => 'نام مرحله را وارد کن.', 'newName.max' => 'نام نباید بیشتر از :max حرف باشد.'],
        );

        (new SavePhase)->handle($this->project, null, $this->newName);

        $this->reset('newName');
        unset($this->phases);
        $this->notify('مرحله اضافه شد.');
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();

        $phase = $this->phase($id);

        $this->resetValidation();
        $this->phaseId = $phase->id;
        $this->name = $phase->name;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate(
            ['name' => ['required', 'string', 'max:80']],
            ['name.required' => 'نام مرحله را وارد کن.', 'name.max' => 'نام نباید بیشتر از :max حرف باشد.'],
        );

        (new SavePhase)->handle($this->project, $this->phase($this->phaseId), $this->name);

        unset($this->phases);
        $this->closeForm();
        $this->notify('نام مرحله ذخیره شد.');
    }

    /* ---------------- ترتیب و حذف ---------------- */

    /** handler ‏wire:sort: (شناسه‌ی مرحله، جایگاه جدید از صفر) */
    public function sortPhase(int $id, int $position): void
    {
        $this->authorizeManage();

        (new ReorderPhases)->handle($this->project, $this->phase($id)->id, $position);

        unset($this->phases);
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();

        (new DeletePhase)->handle($this->phase($id));

        unset($this->phases, $this->unphased);
        $this->dispatch('task-updated');
        $this->notify('مرحله حذف شد.');
    }

    /* ---------------- قالب ---------------- */

    public function applyTemplate(): void
    {
        $this->authorizeManage();

        $this->validate(
            ['template' => ['required', Rule::in(array_keys(array_diff_key(config('orbit.phase_templates', []), ['blank' => 1])))]],
            ['template.in' => 'قالب انتخاب‌شده معتبر نیست.'],
        );

        (new ApplyPhaseTemplate)->handle($this->project, $this->template);

        unset($this->phases);
        $this->notify('مراحل قالب اضافه شد.');
    }
};
?>

@php
    $phases = $this->phases;
    $canManage = ! $project->isArchived() && auth()->user()->can('update', $project);

    $templates = collect(config('orbit.phase_templates', []))->except('blank');
    $templateOptions = $templates->map(fn ($t) => $t['label'])->all();
    $previewPhases = $templates[$template]['phases'] ?? [];
@endphp

<div class="space-y-4">
    <div class="max-w-2xl">
        <p class="text-sm leading-relaxed text-muted">
            مراحل، ساختار کلی کار پروژه‌اند (مثلاً طراحی، فرانت‌اند، تست). هر تسک می‌تواند به یک مرحله وصل شود و در برد و لیست بر اساس آن فیلتر می‌شود.
        </p>
    </div>

    <x-ui.flash :message="$flash" :error="$flashIsError" wire:key="flash-{{ $flashId }}" />

    @if ($canManage)
        <form wire:submit="add" class="flex max-w-2xl items-start gap-3" novalidate>
            <div class="min-w-0 flex-1 space-y-1.5">
                <input
                    type="text"
                    wire:model="newName"
                    class="input"
                    placeholder="نام مرحله‌ی جدید، مثلاً: طراحی UI"
                    autocomplete="off"
                    aria-label="نام مرحله‌ی جدید"
                    aria-invalid="{{ $errors->has('newName') ? 'true' : 'false' }}"
                />
                @error('newName')
                <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="btn-primary shrink-0" wire:loading.attr="disabled" wire:target="add">
                <x-ui.icon name="plus" class="size-4.5" />
                افزودن
            </button>
        </form>
    @endif

    @if ($phases->isEmpty())
        <div class="card">
            <x-ui.empty-state title="این پروژه مرحله‌ای ندارد" icon="layers">
                {{ $canManage ? 'یک مرحله بالا اضافه کن یا مراحل آماده‌ی یک قالب را اضافه کن.' : '' }}
            </x-ui.empty-state>
        </div>
    @else
        <div class="card max-w-3xl">
            <ul
                @if ($canManage)
                    wire:sort="sortPhase"
                @endif
                class="divide-y divide-line"
            >
                @foreach ($phases as $i => $p)
                    @php $progress = $p->tasks_count > 0 ? (int) round($p->done_tasks_count / $p->tasks_count * 100) : 0; @endphp

                    <li
                        wire:key="phase-{{ $p->id }}"
                        @if ($canManage) wire:sort:item="{{ $p->id }}" @endif
                        class="flex flex-wrap items-center gap-x-3 gap-y-2 bg-surface px-4 py-3.5"
                    >
                        @if ($canManage)
                            <span wire:sort:handle class="flex size-8 shrink-0 cursor-grab items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg active:cursor-grabbing" title="برای تغییر ترتیب بکش" aria-label="تغییر ترتیب {{ $p->name }}">
                                <x-ui.icon name="grip" class="size-4.5" />
                            </span>
                        @endif

                        <span class="tabular flex size-7 shrink-0 items-center justify-center rounded-full bg-line/60 text-xs font-medium text-muted">{{ $i + 1 }}</span>

                        <div class="min-w-0 flex-1 basis-40">
                            <p class="truncate text-sm font-medium">{{ $p->name }}</p>
                            <div class="mt-1.5 flex items-center gap-3">
                                <x-ui.progress :value="$progress" :tone="$progress >= 100 ? 'success' : 'primary'" class="max-w-40 flex-1" />
                                <span class="tabular text-xs text-muted">
                                    {{ $p->done_tasks_count }} از {{ $p->tasks_count }} تسک
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-0.5" wire:sort:ignore>
                            <a
                                href="{{ route('projects.show', ['project' => $project, 'tab' => 'board', 'phase' => $p->id]) }}"
                                wire:navigate
                                class="flex size-9 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg"
                                title="مشاهده‌ی تسک‌ها در برد"
                                aria-label="مشاهده‌ی تسک‌های {{ $p->name }}"
                            >
                                <x-ui.icon name="columns" class="size-4.5" />
                            </a>

                            @if ($canManage)
                                <button type="button" wire:click="edit({{ $p->id }})" class="flex size-9 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg" title="تغییر نام" aria-label="تغییر نام {{ $p->name }}">
                                    <x-ui.icon name="edit" class="size-4.5" />
                                </button>

                                <button
                                    type="button"
                                    @click="$store.confirm.ask({
                                        title: @js('حذف مرحله‌ی «'.$p->name.'»'),
                                        message: @js($p->tasks_count > 0
                                            ? $p->tasks_count.' تسک این مرحله حذف نمی‌شوند و «بدون مرحله» می‌مانند.'
                                            : 'این مرحله حذف می‌شود.'),
                                        confirmLabel: 'حذف مرحله',
                                        tone: 'danger',
                                        action: () => $wire.delete({{ $p->id }}),
                                    })"
                                    class="flex size-9 items-center justify-center rounded-control text-muted transition hover:bg-danger/10 hover:text-danger"
                                    title="حذف"
                                    aria-label="حذف {{ $p->name }}"
                                >
                                    <x-ui.icon name="trash" class="size-4.5" />
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($this->unphased > 0)
                <div class="flex items-center justify-between gap-3 border-t border-line bg-canvas/50 px-4 py-3 text-sm">
                    <span class="text-muted"><span class="tabular font-medium text-fg">{{ $this->unphased }}</span> تسک باز بدون مرحله</span>
                    <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'board', 'phase' => 'none']) }}" wire:navigate class="text-primary transition hover:underline">مشاهده</a>
                </div>
            @endif
        </div>
    @endif

    {{-- افزودن از قالب --}}
    @if ($canManage && $templates->isNotEmpty())
        <div class="card max-w-3xl space-y-4 p-5">
            <div>
                <h3 class="text-sm font-bold">افزودن از قالب</h3>
                <p class="mt-0.5 text-xs text-muted">
                    {{ $phases->isEmpty() ? 'مراحل آماده‌ی یک قالب را اضافه کن.' : 'مراحل قالب به انتهای مراحل فعلی اضافه می‌شود و چیزی حذف نمی‌شود.' }}
                </p>
            </div>

            <div class="flex flex-wrap items-start gap-3">
                <x-ui.select wire:model.live="template" :options="$templateOptions" label="قالب" class="w-56" />
                <button type="button" wire:click="applyTemplate" class="btn-secondary" wire:loading.attr="disabled" wire:target="applyTemplate">
                    <x-ui.icon name="plus" class="size-4.5" />
                    افزودن مراحل قالب
                </button>
            </div>

            @if ($previewPhases)
                <ol class="flex flex-wrap items-center gap-2 text-xs">
                    @foreach ($previewPhases as $n => $phaseName)
                        <li class="inline-flex items-center gap-1.5 rounded-full bg-line/60 px-3 py-1.5">
                            <span class="tabular text-muted">{{ $n + 1 }}</span>
                            {{ $phaseName }}
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    @endif

    {{-- مودال تغییر نام --}}
    @if ($showForm)
        <form wire:submit="save" novalidate>
            <x-ui.modal title="تغییر نام مرحله" :description="$project->name" close="closeForm" size="max-w-md">
                <div class="space-y-1.5">
                    <label for="phase-name" class="text-sm font-medium">نام</label>
                    <input
                        id="phase-name"
                        type="text"
                        wire:model="name"
                        x-init="$nextTick(() => { $el.focus(); $el.select() })"
                        class="input"
                        autocomplete="off"
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                    />
                    @error('name')
                    <p class="text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <x-slot:footer>
                    <button type="button" wire:click="closeForm" class="btn-secondary">انصراف</button>
                    <button type="submit" class="btn-primary">ذخیره</button>
                </x-slot:footer>
            </x-ui.modal>
        </form>
    @endif
</div>
