<?php

use App\Models\ChecklistItem;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Task $task;

    public string $newTitle = '';

    public ?int $editingId = null;

    public string $editTitle = '';

    public function mount(Task $task): void
    {
        $this->task = $task;
    }

    #[Computed]
    public function items()
    {
        return $this->task->checklistItems()->get();
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

    private function item(int $id): ChecklistItem
    {
        return ChecklistItem::query()->where('task_id', $this->task->id)->findOrFail($id);
    }

    public function add(): void
    {
        $this->guard();

        $this->validate(
            ['newTitle' => ['required', 'string', 'max:200']],
            ['newTitle.required' => 'متن مورد را وارد کن.', 'newTitle.max' => 'متن نباید بیشتر از :max حرف باشد.'],
        );

        $max = (int)ChecklistItem::query()->where('task_id', $this->task->id)->max('position');

        ChecklistItem::query()->create([
            'task_id' => $this->task->id,
            'title' => trim($this->newTitle),
            'position' => $max + 1,
            'is_done' => false,
        ]);

        $this->reset('newTitle');
        unset($this->items);
        $this->dispatch('task-updated');
    }

    public function toggle(int $id): void
    {
        $this->guard();

        $item = $this->item($id);
        $item->update(['is_done' => !$item->is_done]);

        unset($this->items);
        $this->dispatch('task-updated');
    }

    public function startEdit(int $id): void
    {
        $this->guard();

        $item = $this->item($id);
        $this->editingId = $item->id;
        $this->editTitle = $item->title;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        $this->guard();

        $this->validate(
            ['editTitle' => ['required', 'string', 'max:200']],
            ['editTitle.required' => 'متن مورد را وارد کن.', 'editTitle.max' => 'متن نباید بیشتر از :max حرف باشد.'],
        );

        $this->item($this->editingId)->update(['title' => trim($this->editTitle)]);

        $this->editingId = null;
        unset($this->items);
    }

    public function delete(int $id): void
    {
        $this->guard();

        $this->item($id)->delete();

        unset($this->items);
        $this->dispatch('task-updated');
    }

    /** handler ‏wire:sort */
    public function sortItem(int $id, int $position): void
    {
        $this->guard();

        $ids = ChecklistItem::query()->where('task_id', $this->task->id)
            ->orderBy('position')->orderBy('id')->pluck('id')->map(fn($i) => (int)$i)->all();

        if (!in_array($id, $ids, true)) {
            return;
        }

        $ids = array_values(array_filter($ids, fn($i) => $i !== $id));
        array_splice($ids, max(0, min($position, count($ids))), 0, [$id]);

        foreach ($ids as $i => $itemId) {
            ChecklistItem::query()->whereKey($itemId)->toBase()->update(['position' => $i + 1]);
        }

        unset($this->items);
    }
};
?>

@php
    $items = $this->items;
    $canWrite = ! $this->archived && auth()->user()->can('update', $task);
    $total = $items->count();
    $done = $items->where('is_done', true)->count();
    $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
@endphp

<div>
    @if ($total > 0 || $canWrite)
        <div class="card">
            <div class="flex items-center justify-between gap-3 px-6 pt-5">
                <h2 class="text-base font-bold">چک‌لیست</h2>
                @if ($total > 0)
                    <span class="tabular text-sm text-muted">{{ $done }} از {{ $total }}</span>
                @endif
            </div>

            @if ($total > 0)
                <div class="px-6 pt-3">
                    <x-ui.progress :value="$pct" :tone="$pct >= 100 ? 'success' : 'primary'" />
                </div>
            @endif

            <ul @if ($canWrite) wire:sort="sortItem" @endif class="mt-3 px-3">
                @foreach ($items as $item)
                    <li
                        wire:key="check-{{ $item->id }}"
                        @if ($canWrite) wire:sort:item="{{ $item->id }}" @endif
                        class="group flex items-center gap-3 rounded-control bg-surface px-3 py-2 transition hover:bg-line/30"
                    >
                        <input
                            type="checkbox"
                            wire:click="toggle({{ $item->id }})"
                            @checked($item->is_done)
                            @disabled(! $canWrite)
                            wire:sort:ignore
                            class="size-4.5 shrink-0 rounded border-line accent-primary"
                            aria-label="{{ $item->title }}"
                        />

                        @if ($editingId === $item->id)
                            <div class="min-w-0 flex-1" wire:sort:ignore>
                                <input
                                    type="text"
                                    wire:model="editTitle"
                                    wire:keydown.enter.prevent="saveEdit"
                                    wire:keydown.escape="cancelEdit"
                                    x-init="$nextTick(() => $el.focus())"
                                    class="input h-9"
                                    aria-label="ویرایش مورد"
                                />
                                @error('editTitle')
                                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex shrink-0 items-center gap-0.5" wire:sort:ignore>
                                <button type="button" wire:click="saveEdit"
                                        class="flex size-8 items-center justify-center rounded-control text-success transition hover:bg-success/10"
                                        aria-label="ذخیره">
                                    <x-ui.icon name="check" class="size-4.5"/>
                                </button>
                                <button type="button" wire:click="cancelEdit"
                                        class="flex size-8 items-center justify-center rounded-control text-muted transition hover:bg-line/50"
                                        aria-label="انصراف">
                                    <x-ui.icon name="x" class="size-4.5"/>
                                </button>
                            </div>
                        @else
                            <span @class(['min-w-0 flex-1 text-sm', 'text-muted line-through' => $item->is_done])>{{ $item->title }}</span>

                            @if ($canWrite)
                                <div
                                    class="flex shrink-0 items-center gap-0.5 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100 max-lg:opacity-100"
                                    wire:sort:ignore>
                                    <button type="button" wire:click="startEdit({{ $item->id }})"
                                            class="flex size-8 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg"
                                            aria-label="ویرایش">
                                        <x-ui.icon name="edit" class="size-4"/>
                                    </button>
                                    <button type="button" wire:click="delete({{ $item->id }})"
                                            class="flex size-8 items-center justify-center rounded-control text-muted transition hover:bg-danger/10 hover:text-danger"
                                            aria-label="حذف">
                                        <x-ui.icon name="trash" class="size-4"/>
                                    </button>
                                </div>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($canWrite)
                <form wire:submit="add" class="flex items-start gap-3 px-6 pb-5 pt-3" novalidate>
                    <div class="min-w-0 flex-1">
                        <input type="text" wire:model="newTitle" class="input h-10" placeholder="مورد جدید… (Enter)"
                               autocomplete="off" aria-label="مورد جدید"/>
                        @error('newTitle')
                        <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn-secondary h-10! px-3.5!" aria-label="افزودن">
                        <x-ui.icon name="plus" class="size-4.5"/>
                    </button>
                </form>
            @else
                <div class="pb-3"></div>
            @endif
        </div>
    @endif
</div>
