<?php

use App\Enums\LinkType;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Task $task;

    public bool $showForm = false;

    public string $url = '';

    public string $title = '';

    public string $type = 'auto';

    public function mount(Task $task): void
    {
        $this->task = $task;
    }

    #[Computed]
    public function items()
    {
        return $this->task->links()->get();
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

    public function openForm(): void
    {
        $this->guard();

        $this->reset(['url', 'title']);
        $this->type = 'auto';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->guard();

        $this->validate(
            [
                'url' => ['required', 'url:http,https', 'max:2048'],
                'title' => ['nullable', 'string', 'max:80'],
                'type' => ['required', 'in:auto,figma,repo,staging,other'],
            ],
            [
                'url.required' => 'آدرس لینک را وارد کن.',
                'url.url' => 'آدرس باید با http:// یا https:// شروع شود.',
                'url.max' => 'آدرس خیلی طولانی است.',
                'title.max' => 'عنوان نباید بیشتر از :max حرف باشد.',
            ],
        );

        $this->task->links()->create([
            'type' => $this->type === 'auto' ? null : $this->type,
            'title' => trim($this->title) !== '' ? trim($this->title) : null,
            'url' => trim($this->url),
        ]);

        unset($this->items);
        $this->closeForm();
    }

    public function delete(int $id): void
    {
        $this->guard();

        $this->task->links()->whereKey($id)->delete();

        unset($this->items);
    }
};
?>

@php
    $items = $this->items;
    $canWrite = ! $this->archived && auth()->user()->can('update', $task);
    $icons = ['figma' => 'layout', 'repo' => 'git-branch', 'staging' => 'globe', 'other' => 'link'];

    $typeOptions = ['auto' => 'تشخیص خودکار'];
    foreach (\App\Enums\LinkType::cases() as $t) {
        $typeOptions[$t->value] = $t->label();
    }
@endphp
<div>
    @if ($items->isNotEmpty() || $canWrite)
        <div class="card p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-bold">لینک‌ها</h2>
                @if ($canWrite)
                    <button type="button" wire:click="openForm"
                            class="inline-flex h-8 items-center gap-1.5 rounded-full border border-dashed border-line px-3 text-sm text-muted transition hover:border-primary/40 hover:text-primary">
                        <x-ui.icon name="plus" class="size-3.5"/>
                        لینک
                    </button>
                @endif
            </div>

            @if ($items->isEmpty())
                <p class="mt-3 text-sm text-muted">لینک طراحی (Figma)، ریپازیتوری یا سایت staging مربوط به این تسک را
                    اینجا اضافه کن.</p>
            @else
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($items as $l)
                        <span wire:key="tlink-{{ $l->id }}"
                              class="inline-flex h-9 items-center rounded-full border border-line bg-surface ps-3 {{ $canWrite ? 'pe-1' : 'pe-3' }} text-sm">
                        <a href="{{ $l->url }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 transition hover:text-primary">
                            <x-ui.icon :name="$icons[$l->type->value] ?? 'link'" class="size-4 text-muted"/>
                            {{ $l->displayTitle() }}
                            <x-ui.icon name="external-link" class="size-3.5 text-muted"/>
                        </a>

                        @if ($canWrite)
                                <button
                                    type="button"
                                    @click="$store.confirm.ask({
                                    title: 'حذف لینک',
                                    message: @js('لینک «'.$l->displayTitle().'» حذف شود؟'),
                                    confirmLabel: 'حذف',
                                    tone: 'danger',
                                    action: () => $wire.delete({{ $l->id }}),
                                })"
                                    class="ms-1 flex size-7 items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger"
                                    aria-label="حذف لینک"
                                >
                                <x-ui.icon name="x" class="size-3.5"/>
                            </button>
                            @endif
                    </span>
                    @endforeach
                </div>
            @endif

            @if ($showForm)
                <form wire:submit="save" novalidate>
                    <x-ui.modal title="افزودن لینک" description="Figma، ریپازیتوری، staging یا هر لینک مرجع دیگر."
                                close="closeForm" size="max-w-md">
                        <div class="space-y-5">
                            <div class="space-y-1.5">
                                <label for="tlink-url" class="text-sm font-medium">آدرس</label>
                                <input id="tlink-url" type="url" wire:model="url" x-init="$nextTick(() => $el.focus())"
                                       dir="ltr" class="input text-start" placeholder="https://…" autocomplete="off"
                                       aria-invalid="{{ $errors->has('url') ? 'true' : 'false' }}"/>
                                @error('url') <p class="text-sm text-danger">{{ $message }}</p> @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="tlink-title" class="text-sm font-medium">عنوان <span
                                        class="font-normal text-muted">(اختیاری)</span></label>
                                <input id="tlink-title" type="text" wire:model="title" class="input"
                                       autocomplete="off"/>
                                @error('title') <p class="text-sm text-danger">{{ $message }}</p> @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="tlink-type" class="text-sm font-medium">نوع</label>
                                <x-ui.select id="tlink-type" wire:model="type" :options="$typeOptions"/>
                            </div>
                        </div>

                        <x-slot:footer>
                            <button type="button" wire:click="closeForm" class="btn-secondary">انصراف</button>
                            <button type="submit" class="btn-primary">افزودن</button>
                        </x-slot:footer>
                    </x-ui.modal>
                </form>
            @endif
        </div>
    @endif
</div>
