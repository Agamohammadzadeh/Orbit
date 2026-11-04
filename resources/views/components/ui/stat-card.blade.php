@props(['label', 'value', 'icon', 'tone' => 'default', 'active' => false, 'tag' => 'button', 'hint' => null, 'hintTone' => 'muted'])

@php
    $iconTone = match ($tone) {
        'danger' => 'bg-danger/10 text-danger',
        'success' => 'bg-success/10 text-success',
        'primary' => 'bg-primary-soft text-primary',
        default => 'bg-line/60 text-muted',
    };
    $interactive = $tag === 'button';
@endphp

<{{ $tag }}
    @if ($interactive) type="button" aria-pressed="{{ $active ? 'true' : 'false' }}" @endif
{{ $attributes->class([
    'card flex w-full items-center gap-4 p-4 text-start outline-2 transition',
    'outline-primary' => $active,
    'outline-transparent' => ! $active,
    'hover:outline-primary/30' => ! $active && $interactive,
]) }}
>
<span class="flex size-11 shrink-0 items-center justify-center rounded-control {{ $iconTone }}">
        <x-ui.icon :name="$icon" class="size-5"/>
    </span>
<span class="min-w-0">
        <span class="tabular block text-xl font-bold leading-none">{{ $value }}</span>
        <span class="mt-1.5 block truncate text-sm text-muted">{{ $label }}</span>
        @if ($hint)
            <span @class([
                'mt-0.5 block truncate text-xs',
                'text-success' => $hintTone === 'success',
                'text-danger' => $hintTone === 'danger',
                'text-muted' => $hintTone === 'muted',
            ])>{{ $hint }}</span>
       @endif
    </span>
</{{ $tag }}>
