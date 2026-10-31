@props(['user', 'size' => 36])

@php
    // رنگ ثابت برای هر کاربر (بر اساس id)، از توکن‌های پالت
    $palette = [
        'bg-type-feature/15 text-type-feature',
        'bg-type-improvement/15 text-type-improvement',
        'bg-type-design/15 text-type-design',
        'bg-type-content/15 text-type-content',
        'bg-prio-medium/15 text-prio-medium',
    ];
    $url = $user->avatarUrl();
@endphp

<span
    {{ $attributes->class([
        'inline-flex shrink-0 select-none items-center justify-center overflow-hidden rounded-full font-semibold',
        $palette[$user->id % count($palette)] => ! $url,
    ]) }}
    style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ round($size * 0.38) }}px;"
>
    @if ($url)
        <img src="{{ $url }}" alt="" class="size-full object-cover">
    @else
        {{ $user->initials() }}
    @endif
</span>
