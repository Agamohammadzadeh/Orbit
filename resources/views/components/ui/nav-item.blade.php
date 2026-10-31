@props(['href', 'icon', 'label', 'active' => false])

<a
    href="{{ $href }}"
    wire:navigate
    title="{{ $label }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'flex h-10 items-center gap-3 rounded-control px-3 text-sm font-medium transition lg:sb-collapsed:justify-center lg:sb-collapsed:px-0',
        'bg-primary-soft text-primary' => $active,
        'text-muted hover:bg-line/50 hover:text-fg' => ! $active,
    ]) }}
>
    <x-ui.icon :name="$icon" class="size-5" />
    <span class="truncate lg:sb-collapsed:hidden">{{ $label }}</span>
</a>
