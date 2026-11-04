@props([
    'variant' => 'full',
    'size' => 32,
    'tone' => 'auto',
    'locale' => app()->getLocale(),
    'title' => 'Orbit',
])

@php
    $px = is_numeric($size) ? $size.'px' : $size;

    $mono = $tone === 'mono';
    $white = $tone === 'white';

    $ring = $mono ? 'stroke-current' : ($white ? 'stroke-white' : 'stroke-primary');
    $core = $mono ? 'fill-current' : ($white ? 'fill-white' : 'fill-primary');
    $planet = $mono ? 'fill-current' : 'fill-accent';
    $textTone = $mono ? '' : ($white ? 'text-white' : 'text-fg');

    $isFa = $locale === 'fa';
    $word = $isFa ? 'اوربیت' : 'orbit';
    $withText = $variant !== 'mark';
@endphp

<span
    {{ $attributes->class(['inline-flex items-center', $textTone]) }}
    style="--logo-size: {{ $px }}; gap: calc(var(--logo-size) * 0.25);"
>
    <svg
        viewBox="0 0 64 64"
        fill="none"
        class="shrink-0"
        style="width: var(--logo-size); height: var(--logo-size);"
        @if ($withText)
            aria-hidden="true"
        @else
            role="img" aria-label="{{ $title }}"
        @endif
    >
        <g transform="rotate(-25 32 32)">
            <ellipse cx="32" cy="32" rx="27" ry="10.5" stroke-width="3.5" class="{{ $ring }}" />
            <circle cx="32" cy="32" r="14" class="{{ $core }}" />
            <circle cx="59" cy="32" r="5.5" class="{{ $planet }}" />
        </g>
    </svg>

    @if ($withText)
        <span
            class="{{ $isFa ? 'font-fa' : 'font-brand' }} font-bold leading-none"
            style="font-size: calc(var(--logo-size) * {{ $isFa ? '0.62' : '0.72' }}); letter-spacing: {{ $isFa ? '0' : '-0.03em' }};"
        >{{ $word }}</span>
    @endif
</span>
