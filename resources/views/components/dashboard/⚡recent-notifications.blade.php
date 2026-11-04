<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function items()
    {
        return auth()->user()->notifications()->latest()->limit(5)->get();
    }

    #[Computed]
    public function unread(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    #[On('notifications-updated')]
    public function refresh(): void
    {
        unset($this->items, $this->unread);
    }

    /** اعلان را خوانده می‌کند و به مقصدش می‌رود */
    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->whereKey($id)->first();

        if (!$notification) {
            return;
        }

        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;
        $safe = is_string($url) && str_starts_with($url, '/') ? $url : "#" /*route('notifications')*/
        ;

        $this->redirect($safe, navigate: true);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        unset($this->items, $this->unread);
    }
};
?>

@php
    $icons = [
        'task_assigned' => 'user-plus',
        'mention' => 'at-sign',
        'comment' => 'message',
        'deadline' => 'clock',
    ];
@endphp

<div class="card" wire:poll.60s>
    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
        <h2 class="flex items-center gap-2 text-base font-bold">
            اعلان‌ها
            @if ($this->unread > 0)
                <span
                    class="tabular rounded-full bg-accent px-2 py-0.5 text-[0.6875rem] font-bold leading-none text-white">{{ $this->unread }}</span>
            @endif
        </h2>

        <div class="flex items-center gap-3 text-sm">
            @if ($this->unread > 0)
                <button type="button" wire:click="markAllRead" class="text-muted transition hover:text-primary">خوانده
                    شد
                </button>
            @endif
            <a
                {{--                href="{{ route('notifications') }}" --}}
                href="#"
                wire:navigate
                class="text-muted transition hover:text-primary"
            >
                همه
            </a>
        </div>
    </div>

    @if ($this->items->isEmpty())
        <x-ui.empty-state title="اعلانی نیست" icon="bell">
            ارجاع تسک، منشن و یادآوری ددلاین اینجا نشان داده می‌شود.
        </x-ui.empty-state>
    @else
        <ul class="divide-y divide-line">
            @foreach ($this->items as $n)
                @php $unread = $n->read_at === null; @endphp
                <li wire:key="notification-{{ $n->id }}">
                    <button
                        type="button"
                        wire:click="open('{{ $n->id }}')"
                        class="flex w-full items-start gap-3 px-5 py-3.5 text-start transition hover:bg-line/30"
                    >
                        <span @class([
                            'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full',
                            'bg-primary-soft text-primary' => $unread,
                            'bg-line/60 text-muted' => ! $unread,
                        ])>
                            <x-ui.icon :name="$icons[$n->data['type'] ?? ''] ?? 'bell'" class="size-4"/>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span @class(['line-clamp-2 text-sm', 'font-medium' => $unread, 'text-muted' => ! $unread])>{{ $n->data['message'] ?? 'اعلان جدید' }}</span>
                            <span class="mt-0.5 block text-xs text-muted">{{ $n->created_at->diffForHumans() }}</span>
                        </span>

                        @if ($unread)
                            <span class="mt-2 size-2 shrink-0 rounded-full bg-primary" aria-label="خوانده‌نشده"></span>
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
