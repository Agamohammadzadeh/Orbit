<?php

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Task $task;

    /** @var array<int,\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];

    public function mount(Task $task): void
    {
        $this->task = $task;
    }

    #[Computed]
    public function items()
    {
        return Attachment::query()
            ->where('task_id', $this->task->id)
            ->with('uploader:id,name')
            ->latest()
            ->get();
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

    /** با انتخاب یا رها کردن فایل‌ها، همان لحظه اعتبارسنجی و ذخیره می‌شوند */
    public function updatedFiles(): void
    {
        $this->guard();

        $maxKb = (int)config('orbit.attachments.max_kb');
        $maxCount = (int)config('orbit.attachments.max_per_task');

        try {
            $this->validate(
                [
                    'files' => ['array', 'max:10'],
                    'files.*' => ['file', 'max:' . $maxKb, 'extensions:' . implode(',', config('orbit.attachments.allowed_extensions'))],
                ],
                [
                    'files.max' => 'هر بار حداکثر ۱۰ فایل می‌توانی آپلود کنی.',
                    'files.*.file' => 'آپلود فایل انجام نشد. دوباره امتحان کن.',
                    'files.*.max' => 'حجم هر فایل نباید بیشتر از ' . (int)($maxKb / 1024) . ' مگابایت باشد.',
                    'files.*.extensions' => 'این نوع فایل مجاز نیست.',
                    'files.*.uploaded' => 'آپلود فایل انجام نشد. دوباره امتحان کن.',
                ],
            );
        } catch (ValidationException $e) {
            $this->reset('files');

            throw $e;
        }

        $existing = Attachment::query()->where('task_id', $this->task->id)->count();

        if ($existing + count($this->files) > $maxCount) {
            $this->reset('files');
            $this->addError('files', "هر تسک حداکثر {$maxCount} پیوست می‌تواند داشته باشد.");

            return;
        }

        $disk = config('orbit.attachments.disk');

        foreach ($this->files as $file) {
            $ext = strtolower($file->getClientOriginalExtension());

            $path = $file->storeAs("tasks/{$this->task->id}", Str::uuid() . ($ext ? ".{$ext}" : ''), $disk);

            Attachment::query()->create([
                'task_id' => $this->task->id,
                'user_id' => auth()->id(),
                'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $this->reset('files');
        unset($this->items);
        $this->dispatch('task-updated');
    }

    public function delete(int $id): void
    {
        $this->guard();

        $attachment = Attachment::query()->where('task_id', $this->task->id)->findOrFail($id);

        // فقط کسی که آپلود کرده یا با «حذف هر تسک»
        abort_unless($attachment->user_id === auth()->id() || auth()->user()->can('delete', $this->task), 403);

        $attachment->delete();   // فایل روی دیسک هم پاک می‌شود (Attachment::booted)

        unset($this->items);
        $this->dispatch('task-updated');
    }
};
?>

@php
    $items = $this->items;
    $canWrite = ! $this->archived && auth()->user()->can('update', $task);
    $uploadError = $errors->first('files') ?: collect($errors->get('files.*'))->flatten()->first();
@endphp
<div>
    @if ($items->isNotEmpty() || $canWrite)
        <div class="card p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-bold">پیوست‌ها</h2>
                @if ($items->isNotEmpty())
                    <span class="tabular text-sm text-muted">{{ $items->count() }}</span>
                @endif
            </div>

            @if ($canWrite)
                <div
                    x-data="{ over: false }"
                    @dragover.prevent="over = true"
                    @dragleave.prevent="over = false"
                    @drop.prevent="over = false; $wire.uploadMultiple('files', [...$event.dataTransfer.files])"
                    :class="over ? 'border-primary bg-primary-soft' : 'border-line'"
                    class="mt-4 rounded-control border border-dashed px-4 py-5 text-center transition"
                >
                    <label class="inline-flex flex-wrap items-center justify-center gap-2 text-sm text-muted">
                    <span wire:loading.remove wire:target="files" class="inline-flex items-center gap-2">
                        <x-ui.icon name="upload" class="size-4.5"/>
                        فایل را اینجا رها کن یا
                        <span class="font-medium text-primary hover:underline">انتخاب کن</span>
                    </span>
                        <span wire:loading wire:target="files" class="inline-flex items-center gap-2">
                        <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/>
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"
                                  stroke-linecap="round"/>
                        </svg>
                        در حال آپلود…
                    </span>
                        <input type="file" multiple wire:model="files" class="sr-only"/>
                    </label>
                </div>

                @if ($uploadError)
                    <p class="mt-2 text-sm text-danger" role="alert">{{ $uploadError }}</p>
                @endif
            @endif

            @if ($items->isNotEmpty())
                <ul class="mt-4 divide-y divide-line">
                    @foreach ($items as $a)
                        <li wire:key="attachment-{{ $a->id }}" class="flex min-w-0 items-center gap-3 py-3">
                            @if ($a->isImage() && ! str_contains((string) $a->mime_type, 'svg'))
                                <img src="{{ route('attachments.download', ['attachment' => $a, 'inline' => 1]) }}"
                                     alt="" loading="lazy"
                                     class="size-11 shrink-0 rounded-control border border-line object-cover"/>
                            @else
                                <span
                                    class="flex size-11 shrink-0 items-center justify-center rounded-control bg-line/60 text-muted">
                                <x-ui.icon name="file" class="size-5"/>
                            </span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <a href="{{ route('attachments.download', $a) }}"
                                   class="block truncate text-sm font-medium transition hover:text-primary"
                                   title="{{ $a->original_name }}">{{ $a->original_name }}</a>
                                <p class="mt-0.5 truncate text-xs text-muted">
                                    <span class="tabular">{{ $a->humanSize() }}</span>
                                    · {{ $a->uploader?->name ?? 'کاربر حذف‌شده' }}
                                    · {{ $a->created_at->diffForHumans() }}
                                </p>
                            </div>

                            <a href="{{ route('attachments.download', $a) }}"
                               class="flex size-9 shrink-0 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg"
                               title="دانلود" aria-label="دانلود {{ $a->original_name }}">
                                <x-ui.icon name="download" class="size-4.5"/>
                            </a>

                            @if ($canWrite && ($a->user_id === auth()->id() || auth()->user()->can('delete', $task)))
                                <button
                                    type="button"
                                    @click="$store.confirm.ask({
                                    title: 'حذف پیوست',
                                    message: @js('فایل «'.$a->original_name.'» برای همیشه حذف می‌شود.'),
                                    confirmLabel: 'حذف فایل',
                                    tone: 'danger',
                                    action: () => $wire.delete({{ $a->id }}),
                                })"
                                    class="flex size-9 shrink-0 items-center justify-center rounded-control text-muted transition hover:bg-danger/10 hover:text-danger"
                                    title="حذف"
                                    aria-label="حذف {{ $a->original_name }}"
                                >
                                    <x-ui.icon name="trash" class="size-4.5"/>
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
