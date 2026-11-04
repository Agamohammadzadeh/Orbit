<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('orbit.direction') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    {{-- تم و حالت سایدبار قبل از رندر اعمال می‌شوند تا پرش (flash) نداشته باشیم --}}
    <script>
        (() => {
            try {
                const m = localStorage.getItem('orbit-theme') || 'system';
                const dark = m === 'dark' || (m === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);

                if (localStorage.getItem('orbit-sidebar') === '1') {
                    document.documentElement.classList.add('sb-collapsed');
                }
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body
    class="min-h-dvh bg-canvas text-fg antialiased"
    x-data
    @keydown.escape.window="$store.sidebar.closeMobile()"
>
<livewire:global-search />
<x-ui.confirm-dialog />
<x-ui.toast />
<livewire:project.task-form />

@livewireScripts

<x-layout.sidebar />

{{-- پس‌زمینه‌ی تیره‌ی کشوی منو در موبایل --}}
<div
    x-show="$store.sidebar.mobileOpen"
    x-cloak
    x-transition.opacity
    @click="$store.sidebar.closeMobile()"
    class="fixed inset-0 z-30 bg-black/40 lg:hidden"
></div>

<div class="flex min-h-dvh flex-col transition-[padding] duration-200 lg:ps-(--sidebar-w)">
    <x-layout.header />

    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>
</div>
</body>
</html>
