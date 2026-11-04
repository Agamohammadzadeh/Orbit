@props(['value' => 0, 'tone' => 'primary'])

@php
    $v = max(0, min(100, (int) $value));
    $bar = match ($tone) {
        'success' => 'bg-success',
        'danger' => 'bg-danger',
        default => 'bg-primary',
    };
@endphp

<div
    {{ $attributes->class('h-1.5 w-full  overflow-hidden rounded-full bg-line') }}
    role="progressbar"
    aria-valuenow="{{ $v }}"
    aria-valuemin="0"
    aria-valuemax="100"
>
    <div class="h-full rounded-full transition-[width] duration-300 {{ $bar }}" style="width: {{ $v }}%"></div>
</div>
