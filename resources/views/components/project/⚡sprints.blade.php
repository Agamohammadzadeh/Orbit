<?php

use App\Actions\Sprints\CloseSprint;
use App\Actions\Sprints\SaveSprint;
use App\Actions\Sprints\StartSprint;
use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Project $project;

    /* فرم */
    public bool $showForm = false;

    public ?int $sprintId = null;

    public string $name = '';

    public string $goal = '';

    public string $startsAt = '';

    public string $endsAt = '';

    /* بستن اسپرینت */
    public ?int $closeId = null;

    public string $carry = 'backlog';

    public ?string $flash = null;

    public bool $flashIsError = false;

    public int $flashId = 0;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    #[Computed]
    public function sprints()
    {
        $order = ['active' => 0, 'planned' => 1, 'closed' => 2];

        return $this->project->sprints()
            ->withCount(['tasks', 'tasks as done_tasks_count' => fn ($q) => $q->where('status', 'done')])
            ->get()
            ->sortBy(fn ($s) => $order[$s->status->value])
            ->values();
    }

    #[Computed]
    public function backlogCount(): int
    {
        return Task::query()->where('project_id', $this->project->id)->whereNull('sprint_id')->open()->count();
    }

    #[On('task-saved')]
    public function refresh(): void
    {
        unset($this->sprints, $this->backlogCount);
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

    private function sprint(int $id): Sprint
    {
        return Sprint::query()->where('project_id', $this->project->id)->findOrFail($id);
    }

    /* ---------------- فرم ---------------- */

    public function create(): void
    {
        $this->authorizeManage();

        $this->resetValidation();
        $this->sprintId = null;
        $this->name = 'اسپرینت '.($this->project->sprints()->count() + 1);
        $this->goal = '';
        $this->startsAt = today()->toDateString();
        $this->endsAt = today()->addDays(13)->toDateString();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();

        $sprint = $this->sprint($id);

        $this->resetValidation();
        $this->sprintId = $sprint->id;
        $this->name = $sprint->name;
        $this->goal = (string) $sprint->goal;
        $this->startsAt = $sprint->starts_at->format('Y-m-d');
        $this->endsAt = $sprint->ends_at->format('Y-m-d');
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
            [
                'name' => ['required', 'string', 'min:2', 'max:80'],
                'goal' => ['nullable', 'string', 'max:500'],
                'startsAt' => ['required', 'date_format:Y-m-d'],
                'endsAt' => ['required', 'date_format:Y-m-d', 'after_or_equal:startsAt'],
            ],
            [
                'name.required' => 'نام اسپرینت را وارد کن.',
                'name.min' => 'نام باید حداقل :min حرف باشد.',
                'goal.max' => 'هدف نباید بیشتر از :max حرف باشد.',
                'startsAt.required' => 'تاریخ شروع را انتخاب کن.',
                'startsAt.date_format' => 'تاریخ شروع معتبر نیست.',
                'endsAt.required' => 'تاریخ پایان را انتخاب کن.',
                'endsAt.date_format' => 'تاریخ پایان معتبر نیست.',
                'endsAt.after_or_equal' => 'تاریخ پایان نمی‌تواند قبل از شروع باشد.',
            ],
        );

        $isEdit = $this->sprintId !== null;

        (new SaveSprint)->handle($this->project, $isEdit ? $this->sprint($this->sprintId) : null, [
            'name' => trim($this->name),
            'goal' => trim($this->goal) !== '' ? trim($this->goal) : null,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ]);

        unset($this->sprints);
        $this->closeForm();
        $this->notify($isEdit ? 'اسپرینت ذخیره شد.' : 'اسپرینت ساخته شد.');
    }

    /* ---------------- شروع، بستن، حذف ---------------- */

    public function start(int $id): void
    {
        $this->authorizeManage();

        try {
            (new StartSprint)->handle($this->sprint($id));
        } catch (DomainException $e) {
            $this->notify($e->getMessage(), error: true);

            return;
        }

        unset($this->sprints);
        $this->notify('اسپرینت شروع شد.');
    }

    public function openClose(int $id): void
    {
        $this->authorizeManage();

        $this->closeId = $this->sprint($id)->id;
        $this->carry = 'backlog';
    }

    public function cancelClose(): void
    {
        $this->closeId = null;
    }

    public function confirmClose(): void
    {
        $this->authorizeManage();

        try {
            (new CloseSprint)->handle($this->sprint($this->closeId), $this->carry === 'next' ? 'next' : 'backlog');
        } catch (DomainException $e) {
            $this->notify($e->getMessage(), error: true);
            $this->closeId = null;

            return;
        }

        $this->closeId = null;
        unset($this->sprints, $this->backlogCount);
        $this->dispatch('task-updated');
        $this->notify('اسپرینت بسته شد.');
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();

        $this->sprint($id)->delete();   // تسک‌ها بدون اسپرینت می‌مانند (nullOnDelete)

        unset($this->sprints, $this->backlogCount);
        $this->dispatch('task-updated');
        $this->notify('اسپرینت حذف شد.');
    }
};
?>

@php
    $sprints = $this->sprints;
    $canManage = ! $project->isArchived() && auth()->user()->can('update', $project);
    $hasPlanned = $sprints->contains(fn ($s) => $s->status === \App\Enums\SprintStatus::Planned && $s->id !== $closeId);
    $closing = $closeId ? $sprints->firstWhere('id', $closeId) : null;

    $badge = [
        'active' => 'bg-success/10 text-success',
        'planned' => 'bg-primary-soft text-primary',
        'closed' => 'bg-line/60 text-muted',
    ];
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">
            بک‌لاگ: <span class="tabular font-medium text-fg">{{ $this->backlogCount }}</span> تسک باز بدون اسپرینت
        </p>

        @if ($canManage)
            <button type="button" wire:click="create" class="btn-primary">
                <x-ui.icon name="plus" class="size-4.5" />
                اسپرینت جدید
            </button>
        @endif
    </div>

    <x-ui.flash :message="$flash" :error="$flashIsError" wire:key="flash-{{ $flashId }}" />

    @if ($sprints->isEmpty())
        <div class="card">
            <x-ui.empty-state title="هنوز اسپرینتی نیست" icon="zap">
                {{ $canManage ? 'اسپرینت بازه‌ی زمانی کوتاهی (مثلاً ۲ هفته) است که تسک‌ها را در آن برنامه‌ریزی می‌کنی.' : '' }}
            </x-ui.empty-state>
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($sprints as $s)
                @php
                    $progress = $s->tasks_count > 0 ? (int) round($s->done_tasks_count / $s->tasks_count * 100) : 0;
                    $left = \App\Support\Dates::daysFromToday($s->ends_at);
                @endphp

                <article wire:key="sprint-{{ $s->id }}" @class(['card flex flex-col p-5', 'border-success/40' => $s->status === \App\Enums\SprintStatus::Active])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate font-bold">{{ $s->name }}</h3>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge[$s->status->value] }}">{{ $s->status->label() }}</span>
                            </div>
                            <p class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-muted">
                                <span class="tabular">{{ \App\Support\Dates::short($s->starts_at) }} تا {{ \App\Support\Dates::short($s->ends_at) }}</span>
                                @if ($s->status === \App\Enums\SprintStatus::Active)
                                    <span class="font-medium {{ $left < 0 ? 'text-danger' : 'text-success' }}">
                                        {{ $left < 0 ? abs($left).' روز دیرکرد' : ($left === 0 ? 'امروز تمام می‌شود' : $left.' روز مانده') }}
                                    </span>
                                @endif
                            </p>
                        </div>

                        @if ($canManage)
                            <x-ui.dropdown width="w-48">
                                <x-slot:trigger>
                                    <button type="button" @click="toggle()" :aria-expanded="open" aria-haspopup="menu" class="flex size-8 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg" aria-label="عملیات {{ $s->name }}">
                                        <x-ui.icon name="more" class="size-5" />
                                    </button>
                                </x-slot:trigger>

                                <x-ui.dropdown-item icon="edit" wire:click="edit({{ $s->id }})">ویرایش</x-ui.dropdown-item>
                                <button
                                    type="button"
                                    role="menuitem"
                                    @click="$store.confirm.ask({
                                        title: @js('حذف «'.$s->name.'»'),
                                        message: 'اسپرینت حذف می‌شود. تسک‌هایش حذف نمی‌شوند و به بک‌لاگ برمی‌گردند.',
                                        confirmLabel: 'حذف اسپرینت',
                                        tone: 'danger',
                                        action: () => $wire.delete({{ $s->id }}),
                                    })"
                                    class="flex h-9 w-full items-center gap-2.5 rounded-control px-3 text-start text-sm text-danger transition hover:bg-danger/10 focus:outline-none focus-visible:bg-danger/10"
                                >
                                    <x-ui.icon name="trash" class="size-4.5" />
                                    حذف
                                </button>
                            </x-ui.dropdown>
                        @endif
                    </div>

                    @if ($s->goal)
                        <p class="mt-3 text-sm leading-relaxed text-muted">{{ $s->goal }}</p>
                    @endif

                    <div class="mt-4">
                        <div class="flex items-center justify-between text-xs text-muted">
                            <span><span class="tabular font-medium text-fg">{{ $s->done_tasks_count }}</span> از <span class="tabular">{{ $s->tasks_count }}</span> تسک انجام شده</span>
                            <span class="tabular">{{ $progress }}%</span>
                        </div>
                        <x-ui.progress :value="$progress" :tone="$progress >= 100 ? 'success' : 'primary'" class="mt-2" />
                    </div>

                    <div class="mt-auto flex flex-wrap items-center gap-2 pt-4">
                        <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'board', 'sprint' => $s->id]) }}" wire:navigate class="btn-secondary h-9! px-3.5! text-sm!">
                            <x-ui.icon name="columns" class="size-4" />
                            مشاهده در برد
                        </a>

                        @if ($canManage && $s->status === \App\Enums\SprintStatus::Planned)
                            <button type="button" wire:click="start({{ $s->id }})" class="btn-primary h-9! px-3.5! text-sm!">
                                <x-ui.icon name="play" class="size-4" />
                                شروع
                            </button>
                        @elseif ($canManage && $s->status === \App\Enums\SprintStatus::Active)
                            <button type="button" wire:click="openClose({{ $s->id }})" class="btn-secondary h-9! px-3.5! text-sm!">
                                <x-ui.icon name="check-circle" class="size-4" />
                                بستن اسپرینت
                            </button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    {{-- مودال ساخت و ویرایش --}}
    @if ($showForm)
        <form wire:submit="save" novalidate>
            <x-ui.modal :title="$sprintId ? 'ویرایش اسپرینت' : 'اسپرینت جدید'" :description="$project->name" close="closeForm" size="max-w-md">
                <div class="space-y-5">
                    <div class="space-y-1.5">
                        <label for="sprint-name" class="text-sm font-medium">نام</label>
                        <input id="sprint-name" type="text" wire:model="name" x-init="$nextTick(() => $el.focus())" class="input" autocomplete="off" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />
                        @error('name') <p class="text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="sprint-goal" class="text-sm font-medium">هدف <span class="font-normal text-muted">(اختیاری)</span></label>
                        <textarea id="sprint-goal" wire:model="goal" rows="3" class="input h-auto! min-h-20 resize-y py-2.5" placeholder="مثلاً: تحویل نسخه‌ی اولیه‌ی صفحات اصلی"></textarea>
                        @error('goal') <p class="text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="sprint-start" class="text-sm font-medium">شروع</label>
                            <x-ui.date-field id="sprint-start" wire:model="startsAt" :clearable="false" />
                            @error('startsAt') <p class="text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label for="sprint-end" class="text-sm font-medium">پایان</label>
                            <x-ui.date-field id="sprint-end" wire:model="endsAt" :clearable="false" />
                            @error('endsAt') <p class="text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <x-slot:footer>
                    <button type="button" wire:click="closeForm" class="btn-secondary">انصراف</button>
                    <button type="submit" class="btn-primary">{{ $sprintId ? 'ذخیره' : 'ساخت اسپرینت' }}</button>
                </x-slot:footer>
            </x-ui.modal>
        </form>
    @endif

    {{-- مودال بستن اسپرینت --}}
    @if ($closing)
        @php $openCount = $closing->tasks_count - $closing->done_tasks_count; @endphp

        <form wire:submit="confirmClose">
            <x-ui.modal :title="'بستن «'.$closing->name.'»'" description="تسک‌های ناتمام این اسپرینت چه شوند؟" close="cancelClose" size="max-w-md">
                @if ($openCount === 0)
                    <p class="text-sm text-muted">همه‌ی تسک‌های این اسپرینت انجام شده‌اند.</p>
                @else
                    <p class="mb-4 text-sm"><span class="tabular font-bold">{{ $openCount }}</span> تسک ناتمام دارد.</p>

                    <div class="space-y-2.5">
                        <label class="flex cursor-pointer items-start gap-3 rounded-control border border-line p-3.5 transition has-checked:border-primary/50 has-checked:bg-primary-soft">
                            <input type="radio" wire:model="carry" value="backlog" class="mt-0.5 size-4 accent-primary" />
                            <span>
                                <span class="block text-sm font-medium">برگشت به بک‌لاگ</span>
                                <span class="mt-0.5 block text-xs text-muted">از اسپرینت خارج می‌شوند و بعداً دوباره برنامه‌ریزی می‌کنی.</span>
                            </span>
                        </label>

                        <label @class([
                            'flex items-start gap-3 rounded-control border border-line p-3.5 transition has-checked:border-primary/50 has-checked:bg-primary-soft',
                            'cursor-pointer' => $hasPlanned,
                            'opacity-60' => ! $hasPlanned,
                        ])>
                            <input type="radio" wire:model="carry" value="next" @disabled(! $hasPlanned) class="mt-0.5 size-4 accent-primary" />
                            <span>
                                <span class="block text-sm font-medium">انتقال به اسپرینت بعدی</span>
                                <span class="mt-0.5 block text-xs text-muted">
                                    {{ $hasPlanned ? 'به نزدیک‌ترین اسپرینت برنامه‌ریزی‌شده می‌روند.' : 'اسپرینت برنامه‌ریزی‌شده‌ای وجود ندارد.' }}
                                </span>
                            </span>
                        </label>
                    </div>
                @endif

                <x-slot:footer>
                    <button type="button" wire:click="cancelClose" class="btn-secondary">انصراف</button>
                    <button type="submit" class="btn-primary">بستن اسپرینت</button>
                </x-slot:footer>
            </x-ui.modal>
        </form>
    @endif
</div>
