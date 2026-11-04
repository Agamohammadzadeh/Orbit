<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Phase;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

new class extends Component {
    public Task $task;

    public function mount(Task $task): void
    {
        $this->task = $task;
    }

    #[Computed]
    public function activities()
    {
        return Activity::forSubject($this->task)->with('causer')->latest()->limit(40)->get();
    }

    /** نام مسئول، مرحله و اسپرینت‌های ذکرشده در تغییرها (به‌صورت دسته‌ای) */
    #[Computed]
    public function lookups(): array
    {
        $ids = ['assignee_id' => [], 'phase_id' => [], 'sprint_id' => []];

        foreach ($this->activities as $a) {
            $c = collect($a->attribute_changes)->toArray();

            foreach (['attributes', 'old'] as $side) {
                foreach (array_keys($ids) as $field) {
                    if (! empty($c[$side][$field])) {
                        $ids[$field][] = $c[$side][$field];
                    }
                }
            }
        }

        return [
            'assignee_id' => User::query()->whereIn('id', array_unique($ids['assignee_id']))->pluck('name', 'id')->all(),
            'phase_id' => Phase::query()->whereIn('id', array_unique($ids['phase_id']))->pluck('name', 'id')->all(),
            'sprint_id' => Sprint::query()->whereIn('id', array_unique($ids['sprint_id']))->pluck('name', 'id')->all(),
        ];
    }
};
?>

@php
    $labels = [
        'title' => 'عنوان',
        'status' => 'وضعیت',
        'priority' => 'اولویت',
        'type' => 'نوع',
        'assignee_id' => 'مسئول',
        'due_date' => 'ددلاین',
        'sprint_id' => 'اسپرینت',
        'phase_id' => 'مرحله',
        'estimate_minutes' => 'برآورد',
    ];

    $lookups = $this->lookups;

    $value = function (string $field, $v) use ($lookups): string {
        if ($v === null || $v === '') {
            return '—';
        }

        return match ($field) {
            'status' => TaskStatus::tryFrom((string) $v)?->label() ?? (string) $v,
            'priority' => TaskPriority::tryFrom((string) $v)?->label() ?? (string) $v,
            'type' => TaskType::tryFrom((string) $v)?->label() ?? (string) $v,
            'assignee_id', 'phase_id', 'sprint_id' => $lookups[$field][$v] ?? 'حذف‌شده',
            'due_date' => \App\Support\Dates::short(\Illuminate\Support\Carbon::parse($v)),
            'estimate_minutes' => round(((int) $v) / 60, 2).' ساعت',
            default => (string) $v,
        };
    };

    $activities = $this->activities;
@endphp

<div>
    @if ($activities->isEmpty())
        <x-ui.empty-state title="تاریخچه‌ای ثبت نشده" icon="clock">
            تغییرهای بعدی تسک (وضعیت، مسئول، ددلاین و...) اینجا نشان داده می‌شود.
        </x-ui.empty-state>
    @else
        <ul class="divide-y divide-line">
            @foreach ($activities as $a)
                @php
                    $event = $a->event instanceof \BackedEnum ? $a->event->value : (string) $a->event;
                    $changes = collect($a->attribute_changes)->toArray();
                    $new = $changes['attributes'] ?? [];
                    $old = $changes['old'] ?? [];
                @endphp

                <li wire:key="activity-{{ $a->id }}" class="flex gap-3 px-6 py-4">
                    @if ($a->causer instanceof \App\Models\User)
                        <x-ui.avatar :user="$a->causer" :size="32" />
                    @else
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-line/60 text-muted"><x-ui.icon name="clock" class="size-4" /></span>
                    @endif

                    <div class="min-w-0 flex-1 text-sm">
                        <p>
                            <span class="font-medium">{{ $a->causer?->name ?? 'سیستم' }}</span>
                            @if ($event === 'created')
                                این تسک را ساخت.
                            @else
                                این تسک را ویرایش کرد.
                            @endif
                            <span class="ms-1 text-xs text-muted" title="{{ \App\Support\Dates::short($a->created_at) }}">{{ $a->created_at->diffForHumans() }}</span>
                        </p>

                        @if ($event !== 'created' && $new)
                            <ul class="mt-1.5 space-y-1 text-muted">
                                @foreach ($new as $field => $to)
                                    @if (isset($labels[$field]))
                                        <li class="flex flex-wrap items-center gap-x-1.5">
                                            <span>{{ $labels[$field] }}:</span>
                                            <span class="rounded bg-line/60 px-1.5 py-0.5 text-xs line-through">{{ $value($field, $old[$field] ?? null) }}</span>
                                            <x-ui.icon name="arrow-left" class="size-3.5 rtl:rotate-0" />
                                            <span class="rounded bg-primary-soft px-1.5 py-0.5 text-xs font-medium text-primary">{{ $value($field, $to) }}</span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
