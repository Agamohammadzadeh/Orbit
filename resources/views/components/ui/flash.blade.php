@props(['message' => null, 'error' => false, 'seconds' => 5])

@if ($message)
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, {{ (int) $seconds * 1000 }})"
        x-show="show"
        x-transition.opacity
        {{ $attributes->class([
            'flex items-center gap-2.5 rounded-control border py-2.5 ps-3.5 pe-2 text-sm',
            'border-danger/30 bg-danger/10 text-danger' => $error,
            'border-success/30 bg-success/10 text-success' => ! $error,
        ]) }}
        role="status"
    >
        <x-ui.icon :name="$error ? 'alert-triangle' : 'check'" class="size-4.5" />

        <span class="min-w-0 flex-1">{{ $message }}</span>

        <button
            type="button"
            @click="show = false"
            class="flex size-7 shrink-0 items-center justify-center rounded-md opacity-70 transition hover:bg-current/10 hover:opacity-100"
            aria-label="بستن پیام"
        >
            <x-ui.icon name="x" class="size-4" />
        </button>
    </div>
@endif
