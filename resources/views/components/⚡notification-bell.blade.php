<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function unread(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    #[On('notifications-updated')]
    public function refresh(): void
    {
        unset($this->unread);
    }
};
?>

<a
    href="{{ route('notifications') }}"
    wire:navigate
    wire:poll.30s
    class="relative flex size-10 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg"
    aria-label="اعلان‌ها{{ $this->unread > 0 ? ' ('.$this->unread.' خوانده‌نشده)' : '' }}"
>
    <x-ui.icon name="bell" class="size-5"/>

    @if ($this->unread > 0)
        <span
            class="absolute -end-0.5 -top-0.5 flex h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full bg-accent px-1 text-[0.625rem] font-bold leading-none text-white ring-2 ring-surface">
            {{ $this->unread > 99 ? '99+' : $this->unread }}
        </span>
    @endif
</a>
