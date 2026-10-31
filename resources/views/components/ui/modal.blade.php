@props([
    'title',
    'description' => null,
    'close' => 'closeForm',
    'size' => 'max-w-lg',
])

<div
    x-data="{ show: false }"
    x-init="$nextTick(() => show = true)"
    @keydown.escape.window="$wire.{{ $close }}()"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    aria-label="{{ $title }}"
>
    {{-- پس‌زمینه --}}
    <div
        x-show="show"
        x-cloak
        x-transition.opacity
        wire:click="{{ $close }}"
        class="fixed inset-0 bg-black/40"
    ></div>

    <div class="pointer-events-none relative flex min-h-full items-start justify-center px-4 py-[8vh]">
        <div
            x-show="show"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="panel pointer-events-auto relative w-full {{ $size }}"
        >
            <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
                <div class="min-w-0">
                    <h2 class="text-base font-bold">{{ $title }}</h2>
                    @if ($description)
                        <p class="mt-0.5 text-sm text-muted">{{ $description }}</p>
                    @endif
                </div>
                <button
                    type="button"
                    wire:click="{{ $close }}"
                    class="-me-2 flex size-9 shrink-0 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg"
                    aria-label="بستن"
                >
                    <x-ui.icon name="x" class="size-5" />
                </button>
            </div>

            <div class="px-6 py-5">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex items-center justify-end gap-2.5 rounded-b-panel border-t border-line bg-canvas/50 px-6 py-4">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
