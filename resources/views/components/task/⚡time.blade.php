<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Task $task;

    public string $hours = '';

    public string $mins = '';

    public string $workedOn = '';

    public string $note = '';

    public function mount(Task $task): void
    {
        $this->task = $task;
        $this->workedOn = today()->toDateString();
    }

    #[Computed]
    public function entries()
    {
        return TimeEntry::query()
            ->where('task_id', $this->task->id)
            ->with('user:id,name,avatar_path')
            ->latest('worked_on')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function total(): int
    {
        return (int) TimeEntry::query()->where('task_id', $this->task->id)->sum('minutes');
    }

    #[Computed]
    public function archived(): bool
    {
        return Project::query()->whereKey($this->task->project_id)->whereNotNull('archived_at')->exists();
    }

    private function guard(): void
    {
        Gate::authorize('update', $this->task);
        abort_if($this->archived, 403);
    }

    public function add(): void
    {
        $this->guard();

        $max = (int) config('orbit.time_tracking.max_entry_minutes', 960);

        $this->validate(
            [
                'hours' => ['nullable', 'integer', 'min:0', 'max:16'],
                'mins' => ['nullable', 'integer', 'min:0', 'max:59'],
                'workedOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'note' => ['nullable', 'string', 'max:500'],
            ],
            [
                'hours.integer' => 'ساعت باید عدد صحیح باشد.',
                'hours.max' => 'ساعت نباید بیشتر از ۱۶ باشد.',
                'mins.integer' => 'دقیقه باید عدد صحیح باشد.',
                'mins.max' => 'دقیقه باید بین ۰ تا ۵۹ باشد.',
                'workedOn.required' => 'تاریخ را انتخاب کن.',
                'workedOn.before_or_equal' => 'تاریخ نمی‌تواند در آینده باشد.',
                'note.max' => 'توضیح نباید بیشتر از :max حرف باشد.',
            ],
        );

        $minutes = ((int) $this->hours) * 60 + (int) $this->mins;

        if ($minutes < 1 || $minutes > $max) {
            $this->addError('hours', $minutes < 1 ? 'مدت زمان را وارد کن.' : 'هر ثبت حداکثر '.(int) ($max / 60).' ساعت است.');

            return;
        }

        TimeEntry::query()->create([
            'task_id' => $this->task->id,
            'user_id' => auth()->id(),
            'minutes' => $minutes,
            'worked_on' => $this->workedOn,
            'note' => trim($this->note) !== '' ? trim($this->note) : null,
        ]);

        $this->reset(['hours', 'mins', 'note']);
        unset($this->entries, $this->total);
        $this->dispatch('time-logged');
    }

    public function delete(int $id): void
    {
        $this->guard();

        $entry = TimeEntry::query()->where('task_id', $this->task->id)->findOrFail($id);

        abort_unless($entry->user_id === auth()->id() || auth()->user()->can('delete', $this->task), 403);

        $entry->delete();

        unset($this->entries, $this->total);
        $this->dispatch('time-logged');
    }
};
?>

@php
    $entries = $this->entries;
    $canWrite = ! $this->archived && auth()->user()->can('update', $task);

    $fmt = function (int $m): string {
        if ($m <= 0) {
            return '0';
        }

        return collect([intdiv($m, 60) ? intdiv($m, 60).' ساعت' : null, $m % 60 ? ($m % 60).' دقیقه' : null])
            ->filter()->implode(' و ');
    };
@endphp

<div>
    @if ($canWrite)
        <form wire:submit="add" class="space-y-4 border-b border-line px-6 py-5" novalidate>
            <div class="grid gap-4 sm:grid-cols-[6rem_6rem_minmax(0,1fr)]">
                <div class="space-y-1.5">
                    <label for="time-hours" class="text-sm font-medium">ساعت</label>
                    <input id="time-hours" type="number" min="0" max="16" wire:model="hours" dir="ltr" class="input text-start" placeholder="0" />
                </div>
                <div class="space-y-1.5">
                    <label for="time-mins" class="text-sm font-medium">دقیقه</label>
                    <input id="time-mins" type="number" min="0" max="59" wire:model="mins" dir="ltr" class="input text-start" placeholder="0" />
                </div>
                <div class="space-y-1.5">
                    <label for="time-date" class="text-sm font-medium">تاریخ</label>
                    <x-ui.date-field id="time-date" wire:model="workedOn" :clearable="false" />
                </div>
            </div>

            @if ($errors->has('hours') || $errors->has('mins') || $errors->has('workedOn'))
                <p class="text-sm text-danger">{{ $errors->first('hours') ?: ($errors->first('mins') ?: $errors->first('workedOn')) }}</p>
            @endif

            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <input type="text" wire:model="note" class="input" placeholder="توضیح (اختیاری) مثلاً: جلسه با مشتری" autocomplete="off" aria-label="توضیح" />
                    @error('note') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary shrink-0" wire:loading.attr="disabled" wire:target="add">
                    <x-ui.icon name="plus" class="size-4.5" />
                    ثبت زمان
                </button>
            </div>
        </form>
    @endif

    @if ($entries->isEmpty())
        <x-ui.empty-state title="زمانی ثبت نشده" icon="clock">
            {{ $canWrite ? 'مدت کار روی این تسک را اینجا ثبت کن تا با برآورد مقایسه شود.' : '' }}
        </x-ui.empty-state>
    @else
        <div class="flex items-center justify-between border-b border-line bg-canvas/50 px-6 py-2.5 text-sm">
            <span class="text-muted">مجموع</span>
            <span class="font-bold">{{ $fmt($this->total) }}</span>
        </div>

        <ul class="divide-y divide-line">
            @foreach ($entries as $e)
                <li wire:key="time-{{ $e->id }}" class="flex items-center gap-3 px-6 py-3">
                    @if ($e->user)
                        <x-ui.avatar :user="$e->user" :size="28" />
                    @endif

                    <div class="min-w-0 flex-1">
                        <p class="text-sm">
                            <span class="font-medium">{{ $fmt($e->minutes) }}</span>
                            <span class="text-muted">· {{ $e->user?->name ?? 'کاربر حذف‌شده' }}</span>
                        </p>
                        @if ($e->note)
                            <p class="mt-0.5 truncate text-xs text-muted">{{ $e->note }}</p>
                        @endif
                    </div>

                    <span class="tabular shrink-0 text-xs text-muted">{{ \App\Support\Dates::short($e->worked_on) }}</span>

                    @if ($canWrite && ($e->user_id === auth()->id() || auth()->user()->can('delete', $task)))
                        <button
                            type="button"
                            @click="$store.confirm.ask({
                                title: 'حذف ثبت زمان',
                                message: 'این ثبت زمان حذف می‌شود.',
                                confirmLabel: 'حذف',
                                tone: 'danger',
                                action: () => $wire.delete({{ $e->id }}),
                            })"
                            class="flex size-8 shrink-0 items-center justify-center rounded-control text-muted transition hover:bg-danger/10 hover:text-danger"
                            aria-label="حذف"
                        >
                            <x-ui.icon name="trash" class="size-4" />
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
