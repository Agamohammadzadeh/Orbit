<?php

use App\Actions\Comments\AddComment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Support\RichText;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Task $task;

    public string $body = '';

    public ?int $editingId = null;

    public string $editBody = '';

    public function mount(Task $task): void
    {
        $this->task = $task;
    }

    /** اعضای فعال پروژه (برای پیشنهاد منشن و تبدیل نام به توکن) */
    #[Computed]
    public function members()
    {
        return Project::query()->findOrFail($this->task->project_id)
            ->members()->active()->orderBy('name')->get(['users.id', 'users.name']);
    }

    #[Computed]
    public function comments()
    {
        return Comment::query()
            ->where('task_id', $this->task->id)
            ->with('author:id,name,avatar_path')
            ->oldest()
            ->get();
    }

    #[Computed]
    public function archived(): bool
    {
        return Project::query()->whereKey($this->task->project_id)->whereNotNull('archived_at')->exists();
    }

    private function comment(int $id): Comment
    {
        return Comment::query()->where('task_id', $this->task->id)->findOrFail($id);
    }

    public function add(): void
    {
        Gate::authorize('create', [Comment::class, $this->task]);
        abort_if($this->archived, 403);

        $this->validate(
            ['body' => ['required', 'string', 'max:5000']],
            ['body.required' => 'متن کامنت را بنویس.', 'body.max' => 'کامنت نباید بیشتر از :max حرف باشد.'],
        );

        (new AddComment)->handle(auth()->user(), $this->task, $this->body);

        $this->reset('body');
        unset($this->comments);
        $this->dispatch('task-updated');
    }

    public function startEdit(int $id): void
    {
        $comment = $this->comment($id);
        Gate::authorize('update', $comment);

        $this->editingId = $comment->id;
        $this->editBody = RichText::toNames($comment->body, $this->members->pluck('name', 'id')->all());
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        $comment = $this->comment($this->editingId);
        Gate::authorize('update', $comment);
        abort_if($this->archived, 403);

        $this->validate(
            ['editBody' => ['required', 'string', 'max:5000']],
            ['editBody.required' => 'متن کامنت را بنویس.', 'editBody.max' => 'کامنت نباید بیشتر از :max حرف باشد.'],
        );

        $comment->update(['body' => RichText::toTokens(trim($this->editBody), $this->members)]);

        $this->editingId = null;
        unset($this->comments);
    }

    public function delete(int $id): void
    {
        $comment = $this->comment($id);
        Gate::authorize('delete', $comment);
        abort_if($this->archived, 403);

        $comment->delete();

        unset($this->comments);
        $this->dispatch('task-updated');
    }
};
?>

@php
    $comments = $this->comments;
    $names = $this->members->pluck('name', 'id')->all();
    $picker = $this->members->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all();
    $canComment = ! $this->archived && auth()->user()->can('create', [\App\Models\Comment::class, $task]);
@endphp

<div>
    @if ($comments->isEmpty())
        <x-ui.empty-state title="هنوز کامنتی نیست" icon="message">
            {{ $canComment ? 'اولین کامنت را بنویس. با «@» می‌توانی همکارت را منشن کنی.' : '' }}
        </x-ui.empty-state>
    @else
        <ul class="divide-y divide-line">
            @foreach ($comments as $c)
                @php $mine = $c->user_id === auth()->id(); @endphp

                <li wire:key="comment-{{ $c->id }}" class="group flex gap-3 px-6 py-4">
                    @if ($c->author)
                        <x-ui.avatar :user="$c->author" :size="36" />
                    @else
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-line/60 text-muted"><x-ui.icon name="user" class="size-4.5" /></span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                            <span class="text-sm font-medium">{{ $c->author?->name ?? 'کاربر حذف‌شده' }}</span>
                            <span class="text-xs text-muted" title="{{ \App\Support\Dates::short($c->created_at) }}">{{ $c->created_at->diffForHumans() }}</span>
                            @if ($c->updated_at->gt($c->created_at->addMinute()))
                                <span class="text-xs text-muted">(ویرایش‌شده)</span>
                            @endif

                            @if (! $this->archived && $mine && auth()->user()->can('update', $c))
                                <div class="ms-auto flex items-center gap-0.5 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100 max-lg:opacity-100">
                                    <button type="button" wire:click="startEdit({{ $c->id }})" class="flex size-7 items-center justify-center rounded-md text-muted transition hover:bg-line/50 hover:text-fg" aria-label="ویرایش کامنت"><x-ui.icon name="edit" class="size-4" /></button>
                                    <button
                                        type="button"
                                        @click="$store.confirm.ask({
                                            title: 'حذف کامنت',
                                            message: 'این کامنت حذف می‌شود.',
                                            confirmLabel: 'حذف',
                                            tone: 'danger',
                                            action: () => $wire.delete({{ $c->id }}),
                                        })"
                                        class="flex size-7 items-center justify-center rounded-md text-muted transition hover:bg-danger/10 hover:text-danger"
                                        aria-label="حذف کامنت"
                                    ><x-ui.icon name="trash" class="size-4" /></button>
                                </div>
                            @endif
                        </div>

                        @if ($editingId === $c->id)
                            <form wire:submit="saveEdit" class="mt-2 space-y-2" novalidate>
                                <x-ui.mention-textarea wire:model="editBody" :members="$picker" :rows="3" />
                                @error('editBody')
                                <p class="text-sm text-danger">{{ $message }}</p>
                                @enderror
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="btn-primary h-9! px-3.5! text-sm!">ذخیره</button>
                                    <button type="button" wire:click="cancelEdit" class="btn-secondary h-9! px-3.5! text-sm!">انصراف</button>
                                </div>
                            </form>
                        @else
                            <div class="rich mt-1.5 text-sm leading-loose">{!! \App\Support\RichText::render($c->body, $names) !!}</div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canComment)
        <form wire:submit="add" class="space-y-2.5 border-t border-line px-6 py-4" novalidate>
            <x-ui.mention-textarea wire:model="body" :members="$picker" :rows="3" placeholder="کامنت بنویس… (با @ همکار را منشن کن)" />

            @error('body')
            <p class="text-sm text-danger">{{ $message }}</p>
            @enderror

            <div class="flex items-center justify-between gap-3">
                <span class="text-xs text-muted max-sm:hidden">Ctrl + Enter برای ارسال</span>
                <button type="submit" class="btn-primary ms-auto h-10! text-sm!" wire:loading.attr="disabled" wire:target="add">
                    <x-ui.icon name="send" class="size-4 rtl:-scale-x-100" />
                    ارسال
                </button>
            </div>
        </form>
    @endif
</div>
