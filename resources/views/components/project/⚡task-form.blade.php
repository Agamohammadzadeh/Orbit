<?php

use App\Actions\Tasks\SaveTask;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public bool $show = false;

    public ?int $taskId = null;

    public string $projectId = '';

    public string $title = '';

    public string $description = '';

    public string $status = 'todo';

    public string $priority = 'medium';

    public string $type = 'feature';

    public string $assigneeId = '';

    public string $phaseId = '';

    public string $sprintId = '';

    public string $dueDate = '';

    public string $estimateHours = '';

    /** @var array<int,string> */
    public array $labelIds = [];

    /* ---------------- داده‌ها ---------------- */

    /** پروژه‌های فعالی که کاربر می‌تواند در آن‌ها تسک بسازد (id => نام) */
    #[Computed]
    public function projects(): array
    {
        $user = auth()->user();

        if (! $user->can('tasks.write')) {
            return [];
        }

        return Project::query()
            ->visibleTo($user)
            ->notArchived()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->pluck('name', 'id')
            ->all();
    }

    #[Computed]
    public function project(): ?Project
    {
        return ctype_digit($this->projectId) ? Project::query()->find((int) $this->projectId) : null;
    }

    #[Computed]
    public function members()
    {
        return $this->project?->members()->orderBy('name')->get(['users.id', 'users.name']) ?? collect();
    }

    #[Computed]
    public function phases()
    {
        return $this->project?->phases()->get(['id', 'name']) ?? collect();
    }

    #[Computed]
    public function sprints()
    {
        return ($this->project?->sprints()->get(['id', 'name', 'status']) ?? collect())
            ->filter(fn ($s) => $s->status->value !== 'closed' || (string) $s->id === $this->sprintId)
            ->values();
    }

    #[Computed]
    public function labels()
    {
        return Label::query()->orderBy('name')->get(['id', 'name', 'color']);
    }

    private function canWrite(Project $project): bool
    {
        return ! $project->isArchived()
            && auth()->user()->can('create', [Task::class, $project]);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['taskId', 'projectId', 'title', 'description', 'assigneeId', 'phaseId', 'sprintId', 'dueDate', 'estimateHours', 'labelIds']);
        $this->status = 'todo';
        $this->priority = 'medium';
        $this->type = 'feature';
    }

    /** با عوض شدن پروژه، گزینه‌های وابسته (مسئول، مرحله، اسپرینت) خالی می‌شوند */
    public function updatedProjectId(): void
    {
        $this->reset(['assigneeId', 'phaseId', 'sprintId']);
        $this->resetValidation(['assigneeId', 'phaseId', 'sprintId']);
    }

    /* ---------------- باز و بسته ---------------- */

    /**
     * @param  ?string  $status  وضعیت اولیه (از «+» ستون کانبان)
     * @param  ?int  $projectId  پروژه‌ی جاری؛ اگر نباشد، کاربر خودش انتخاب می‌کند
     */
    #[On('open-task-form')]
    public function openCreate(?string $status = null, ?int $projectId = null, ?string $dueDate = null): void
    {
        $allowed = $this->projects;

        if (! $allowed) {
            $this->dispatch('toast', message: 'پروژه‌ی فعالی برای ساخت تسک نداری.', tone: 'error');

            return;
        }

        $this->resetForm();

        // پروژه‌ی جاری یا، اگر فقط یک پروژه هست، همان
        $pid = $projectId && isset($allowed[$projectId])
            ? $projectId
            : (count($allowed) === 1 ? array_key_first($allowed) : null);

        $this->projectId = $pid ? (string) $pid : '';

        if ($status && TaskStatus::tryFrom($status)) {
            $this->status = $status;
        }

        // ددلاین از پیش‌تعیین‌شده (مثلاً روز انتخاب‌شده در تقویم)
        if ($dueDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $this->dueDate = $dueDate;
        }

        $this->show = true;
    }

    #[On('edit-task')]
    public function openEdit(int $id): void
    {
        $task = Task::query()->find($id);
        $project = $task ? Project::query()->find($task->project_id) : null;

        if (! $task || ! $project || ! $this->canWrite($project) || ! auth()->user()->can('update', $task)) {
            return;
        }

        $this->resetForm();

        $this->taskId = $task->id;
        $this->projectId = (string) $task->project_id;
        $this->title = $task->title;
        $this->description = (string) $task->description;
        $this->status = $task->status->value;
        $this->priority = $task->priority->value;
        $this->type = $task->type->value;
        $this->assigneeId = (string) $task->assignee_id;
        $this->phaseId = (string) $task->phase_id;
        $this->sprintId = (string) $task->sprint_id;
        $this->dueDate = $task->due_date?->format('Y-m-d') ?? '';
        $this->estimateHours = $task->estimate_minutes ? (string) round($task->estimate_minutes / 60, 2) : '';
        $this->labelIds = $task->labels()->pluck('labels.id')->map(fn ($i) => (string) $i)->all();

        $this->show = true;
    }

    public function closeForm(): void
    {
        $this->show = false;
        $this->resetForm();
    }

    /* ---------------- ذخیره و حذف ---------------- */

    public function save(): void
    {
        $task = $this->taskId ? Task::query()->findOrFail($this->taskId) : null;
        $pid = (int) $this->projectId;
        $project = $pid ? Project::query()->find($pid) : null;

        if ($project) {
            abort_if($project->isArchived(), 403);
            abort_if($task && $task->project_id !== $project->id, 422);

            $task ? Gate::authorize('update', $task) : Gate::authorize('create', [Task::class, $project]);
        }

        $this->validate(
            [
                'projectId' => ['required', Rule::exists('projects', 'id')],
                'title' => ['required', 'string', 'min:2', 'max:200'],
                'description' => ['nullable', 'string', 'max:10000'],
                'status' => ['required', Rule::enum(TaskStatus::class)],
                'priority' => ['required', Rule::enum(TaskPriority::class)],
                'type' => ['required', Rule::enum(TaskType::class)],
                'assigneeId' => ['nullable', Rule::exists('project_user', 'user_id')->where('project_id', $pid)],
                'phaseId' => ['nullable', Rule::exists('phases', 'id')->where('project_id', $pid)],
                'sprintId' => ['nullable', Rule::exists('sprints', 'id')->where('project_id', $pid)],
                'dueDate' => ['nullable', 'date_format:Y-m-d'],
                'estimateHours' => ['nullable', 'numeric', 'min:0.25', 'max:999'],
                'labelIds' => ['array'],
                'labelIds.*' => ['exists:labels,id'],
            ],
            [
                'projectId.required' => 'یک پروژه انتخاب کن.',
                'projectId.exists' => 'این پروژه وجود ندارد.',
                'title.required' => 'عنوان تسک را وارد کن.',
                'title.min' => 'عنوان باید حداقل :min حرف باشد.',
                'title.max' => 'عنوان نباید بیشتر از :max حرف باشد.',
                'description.max' => 'توضیحات خیلی طولانی است.',
                'assigneeId.exists' => 'مسئول باید عضو این پروژه باشد.',
                'phaseId.exists' => 'این مرحله وجود ندارد.',
                'sprintId.exists' => 'این اسپرینت وجود ندارد.',
                'dueDate.date_format' => 'تاریخ معتبر نیست.',
                'estimateHours.numeric' => 'برآورد باید عدد باشد.',
                'estimateHours.min' => 'برآورد حداقل ربع ساعت است.',
                'estimateHours.max' => 'برآورد خیلی زیاد است.',
            ],
        );

        $isEdit = $task !== null;

        (new SaveTask)->handle(auth()->user(), $project, $task, [
            'title' => trim($this->title),
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
            'status' => $this->status,
            'priority' => $this->priority,
            'type' => $this->type,
            'assignee_id' => $this->assigneeId !== '' ? (int) $this->assigneeId : null,
            'phase_id' => $this->phaseId !== '' ? (int) $this->phaseId : null,
            'sprint_id' => $this->sprintId !== '' ? (int) $this->sprintId : null,
            'due_date' => $this->dueDate !== '' ? $this->dueDate : null,
            'estimate_minutes' => $this->estimateHours !== '' ? (int) round((float) $this->estimateHours * 60) : null,
        ], array_map('intval', $this->labelIds));

        $this->closeForm();
        $this->dispatch('task-saved');
        $this->dispatch('task-updated');
        $this->dispatch('toast', message: $isEdit ? 'تغییرات تسک ذخیره شد.' : 'تسک ساخته شد.');
    }

    public function deleteTask(): void
    {
        $task = Task::query()->findOrFail($this->taskId);
        $project = Project::query()->findOrFail($task->project_id);

        Gate::authorize('delete', $task);
        abort_if($project->isArchived(), 403);

        $task->delete();

        $this->closeForm();
        $this->dispatch('task-saved');
        $this->dispatch('task-updated');
        $this->dispatch('toast', message: 'تسک حذف شد.');
    }
};
?>

<div>
    @if ($show)
        @php
            $isEdit = $taskId !== null;
            $project = $this->project;
            $enumOptions = fn (array $cases) => collect($cases)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();

            $statusOptions = $enumOptions(TaskStatus::cases());
            $priorityOptions = $enumOptions(TaskPriority::cases());
            $typeOptions = $enumOptions(TaskType::cases());
            $projectOptions = ['' => 'انتخاب پروژه'] + $this->projects;
            $memberOptions = ['' => 'بدون مسئول'] + $this->members->pluck('name', 'id')->all();
            $phaseOptions = ['' => 'بدون مرحله'] + $this->phases->pluck('name', 'id')->all();
            $sprintOptions = ['' => 'بدون اسپرینت'] + $this->sprints->pluck('name', 'id')->all();

            $canDelete = $isEdit && auth()->user()->can('delete', \App\Models\Task::find($taskId));
        @endphp

        <form wire:submit="save" novalidate>
            <x-ui.modal
                :title="$isEdit ? 'ویرایش تسک' : 'تسک جدید'"
                :description="$project?->name ?? 'ابتدا یک پروژه انتخاب کن'"
                close="closeForm"
                size="max-w-2xl"
            >
                <div class="space-y-5">
                    {{-- پروژه (فقط هنگام ساخت) --}}
                    @unless ($isEdit)
                        <div class="space-y-1.5">
                            <label for="task-project" class="text-sm font-medium">پروژه</label>
                            <x-ui.select id="task-project" wire:model.live="projectId" :options="$projectOptions" />
                            @error('projectId')
                            <p class="text-sm text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    @endunless

                    @if ($project)
                        <div class="space-y-1.5">
                            <label for="task-title" class="text-sm font-medium">عنوان</label>
                            <input
                                id="task-title"
                                type="text"
                                wire:model="title"
                                x-init="$nextTick(() => $el.focus())"
                                class="input"
                                placeholder="مثلاً: پیاده‌سازی صفحه‌ی ورود"
                                autocomplete="off"
                                aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}"
                            />
                            @error('title')
                            <p class="text-sm text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="task-description" class="text-sm font-medium">توضیحات <span class="font-normal text-muted">(اختیاری)</span></label>
                            <textarea
                                id="task-description"
                                wire:model="description"
                                rows="4"
                                class="input h-auto! min-h-24 resize-y py-2.5"
                                placeholder="جزئیات، معیار پذیرش، لینک طراحی…"
                            ></textarea>
                            @error('description')
                            <p class="text-sm text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-5 sm:grid-cols-3">
                            <div class="space-y-1.5">
                                <label for="task-status" class="text-sm font-medium">وضعیت</label>
                                <x-ui.select id="task-status" wire:model="status" :options="$statusOptions" />
                            </div>
                            <div class="space-y-1.5">
                                <label for="task-priority" class="text-sm font-medium">اولویت</label>
                                <x-ui.select id="task-priority" wire:model="priority" :options="$priorityOptions" />
                            </div>
                            <div class="space-y-1.5">
                                <label for="task-type" class="text-sm font-medium">نوع</label>
                                <x-ui.select id="task-type" wire:model="type" :options="$typeOptions" />
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="task-assignee" class="text-sm font-medium">مسئول</label>
                                <x-ui.select id="task-assignee" wire:model="assigneeId" :options="$memberOptions" />
                                @error('assigneeId')
                                <p class="text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="task-phase" class="text-sm font-medium">مرحله</label>
                                <x-ui.select id="task-phase" wire:model="phaseId" :options="$phaseOptions" />
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-3">
                            <div class="space-y-1.5">
                                <label for="task-sprint" class="text-sm font-medium">اسپرینت</label>
                                <x-ui.select id="task-sprint" wire:model="sprintId" :options="$sprintOptions" />
                            </div>
                            <div class="space-y-1.5">
                                <label for="task-due" class="text-sm font-medium">ددلاین</label>
                                <x-ui.date-field id="task-due" wire:model="dueDate" placeholder="انتخاب تاریخ" />
                                @error('dueDate')
                                <p class="text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="task-estimate" class="text-sm font-medium">برآورد (ساعت)</label>
                                <input
                                    id="task-estimate"
                                    type="number"
                                    step="0.25"
                                    min="0"
                                    wire:model="estimateHours"
                                    dir="ltr"
                                    class="input text-start"
                                    placeholder="مثلاً 4"
                                />
                                @error('estimateHours')
                                <p class="text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        @if ($this->labels->isNotEmpty())
                            <div class="space-y-2">
                                <span class="text-sm font-medium">برچسب‌ها</span>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->labels as $l)
                                        <label wire:key="task-label-{{ $l->id }}">
                                            <input type="checkbox" wire:model="labelIds" value="{{ $l->id }}" class="peer sr-only" />
                                            <span
                                                style="--lc: {{ $l->color }}"
                                                class="inline-flex h-8 items-center gap-2 rounded-full border border-line px-3 text-sm transition peer-checked:border-transparent peer-checked:bg-(--lc) peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-primary"
                                            >
                                                <span class="size-2 rounded-full bg-(--lc)"></span>
                                                {{ $l->name }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @else
                        <p class="rounded-control border border-dashed border-line px-4 py-6 text-center text-sm text-muted">
                            پروژه را انتخاب کن تا بقیه‌ی فیلدها نمایش داده شود.
                        </p>
                    @endif
                </div>

                <x-slot:footer>
                    @if ($canDelete)
                        <button
                            type="button"
                            @click="$store.confirm.ask({
                                title: 'حذف تسک',
                                message: 'تسک با کامنت‌ها و پیوست‌هایش حذف می‌شود. این کار قابل بازگشت نیست.',
                                confirmLabel: 'حذف تسک',
                                tone: 'danger',
                                action: () => $wire.deleteTask(),
                            })"
                            class="me-auto inline-flex h-11 items-center gap-2 rounded-control px-3 text-sm text-danger transition hover:bg-danger/10"
                        >
                            <x-ui.icon name="trash" class="size-4.5" />
                            حذف
                        </button>
                    @endif

                    <button type="button" wire:click="closeForm" class="btn-secondary">انصراف</button>
                    <button type="submit" class="btn-primary" @disabled(! $project) wire:loading.attr="disabled" wire:target="save">
                        <svg wire:loading wire:target="save" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        {{ $isEdit ? 'ذخیره' : 'ساخت تسک' }}
                    </button>
                </x-slot:footer>
            </x-ui.modal>
        </form>
    @endif
</div>
