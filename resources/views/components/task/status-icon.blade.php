@props(['status'])

@php $c = $status->color(); @endphp

<svg {{ $attributes->class('size-5 shrink-0') }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <circle cx="12" cy="12" r="9" stroke="{{ $c }}" stroke-width="2" @if ($status === \App\Enums\TaskStatus::Done) fill="{{ $c }}" @endif />

    @if ($status === \App\Enums\TaskStatus::InProgress)
        <path d="M12 3a9 9 0 0 1 0 18z" fill="{{ $c }}" />
    @elseif ($status === \App\Enums\TaskStatus::InReview)
        <circle cx="12" cy="12" r="4" fill="{{ $c }}" />
    @elseif ($status === \App\Enums\TaskStatus::Done)
        <path d="M7.5 12.5l3 3 6-6.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    @endif
</svg>
