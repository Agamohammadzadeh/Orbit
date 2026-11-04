@props([
    'code',
    'title',
    'message',
    'icon' => 'alert-triangle',
    'tone' => 'primary',
    'reload' => false,
])

@php
    $danger = $tone === 'danger';
@endphp

    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('orbit.direction', 'rtl') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex">

    <title>{{ $title }} · {{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    {{-- همان تمی که کاربر انتخاب کرده --}}
    <script>
        (() => {
            try {
                const m = localStorage.getItem('orbit-theme') || 'system';
                const dark = m === 'dark' || (m === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas text-fg antialiased">
<main class="mx-auto flex min-h-dvh w-full max-w-md flex-col items-center justify-center px-6 py-12 text-center">
    <a href="{{ url('/') }}" aria-label="{{ config('app.name') }}" class="mb-10">
        <x-ui.logo size="36" />
    </a>

    {{-- تصویر: سیاره‌ی مرجانی که دور نماد می‌چرخد --}}
    <div class="relative size-56" aria-hidden="true">
        <div class="absolute inset-0 rounded-full border border-dashed border-line"></div>
        <div class="absolute inset-6 rounded-full border border-line/60"></div>

        <div class="absolute inset-0 animate-[spin_16s_linear_infinite]">
            <span class="absolute left-1/2 top-0 size-4 -translate-x-1/2 -translate-y-1/2 rounded-full bg-accent shadow-card"></span>
        </div>

        <div @class([
                    'absolute inset-10 flex items-center justify-center rounded-full',
                    'bg-primary-soft' => ! $danger,
                    'bg-danger/10' => $danger,
                ])>
            <x-ui.icon :name="$icon" @class(['size-12', 'text-primary' => ! $danger, 'text-danger' => $danger]) />
        </div>
    </div>

    <p @class([
                'tabular mt-8 text-sm font-bold tracking-[0.3em]',
                'text-primary' => ! $danger,
                'text-danger' => $danger,
            ])>{{ $code }}</p>

    <h1 class="mt-2 text-2xl font-bold tracking-tight">{{ $title }}</h1>
    <p class="mt-3 text-sm leading-loose text-muted">{{ $message }}</p>

    {{ $slot }}

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        @if ($reload)
            <button type="button" onclick="location.reload()" class="btn-primary">
                <x-ui.icon name="refresh" class="size-4.5" />
                بارگذاری دوباره
            </button>
            <a href="{{ url('/') }}" class="btn-secondary">صفحه‌ی اصلی</a>
        @else
            <a href="{{ url('/') }}" class="btn-primary">
                <x-ui.icon name="home" class="size-4.5" />
                صفحه‌ی اصلی
            </a>
            <button type="button" onclick="history.length > 1 ? history.back() : (location.href = '{{ url('/') }}')" class="btn-secondary">
                <x-ui.icon name="arrow-left" class="size-4.5 rtl:rotate-180" />
                بازگشت
            </button>
        @endif
    </div>
</main>
</body>
</html>
