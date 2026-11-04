@props(['href' => null, 'icon' => null, 'tone' => 'default', 'type' => 'button', 'navigate' => true])

@php
    $classes = 'flex h-9 w-full items-center gap-2.5 rounded-control px-3 text-start text-sm transition focus:outline-none '
        . ($tone === 'danger'
            ? 'text-danger hover:bg-danger/10 focus-visible:bg-danger/10'
            : 'hover:bg-line/50 focus-visible:bg-line/50');
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" :class="$tone === 'danger' ? 'size-4.5' : 'size-4.5 text-muted'" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" :class="$tone === 'danger' ? 'size-4.5' : 'size-4.5 text-muted'" />
        @endif
        {{ $slot }}
    </button>
@endif
