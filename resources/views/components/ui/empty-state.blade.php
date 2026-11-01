@props(['title', 'icon' => 'check-circle', 'tone' => 'muted'])

<div {{ $attributes->class('flex flex-col items-center px-6 py-12 text-center') }}>
    <span class="flex size-12 items-center justify-center rounded-full {{ $tone === 'success' ? 'bg-success/10 text-success' : 'bg-line/60 text-muted' }}">
        <x-ui.icon :name="$icon" class="size-6" />
    </span>
    <p class="mt-4 font-medium">{{ $title }}</p>
    @if ($slot->isNotEmpty())
        <p class="mt-1 max-w-xs text-sm text-muted">{{ $slot }}</p>
    @endif
</div>
