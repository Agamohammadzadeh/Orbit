<div
    x-show="$store.confirm.open"
    x-cloak
    x-effect="if ($store.confirm.open) $nextTick(() => $refs.cancel?.focus())"
    @keydown.escape.window="$store.confirm.cancel()"
    class="fixed inset-0 z-[60] flex items-center justify-center px-4"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="confirm-title"
    aria-describedby="confirm-message"
>
    {{-- پس‌زمینه --}}
    <div
        x-show="$store.confirm.open"
        x-transition.opacity
        @click="$store.confirm.cancel()"
        class="absolute inset-0 bg-black/40"
    ></div>

    {{-- پنل --}}
    <div
        x-show="$store.confirm.open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="panel relative w-full max-w-sm p-6"
    >
        <div class="flex items-start gap-4">
            <span
                :class="$store.confirm.tone === 'danger' ? 'bg-danger/10 text-danger' : 'bg-primary-soft text-primary'"
                class="flex size-11 shrink-0 items-center justify-center rounded-full"
            >
                <x-ui.icon name="alert-triangle" class="size-5" x-show="$store.confirm.tone === 'danger'" />
                <x-ui.icon name="info" class="size-5" x-show="$store.confirm.tone !== 'danger'" />
            </span>

            <div class="min-w-0 flex-1 pt-0.5">
                <h2 id="confirm-title" class="text-base font-bold" x-text="$store.confirm.title"></h2>
                <p
                    id="confirm-message"
                    class="mt-1.5 text-sm leading-relaxed text-muted"
                    x-show="$store.confirm.message"
                    x-text="$store.confirm.message"
                ></p>
            </div>
        </div>

        {{-- دکمه‌ها: در RTL دکمه‌ی اصلی سمت راست می‌نشیند --}}
        <div class="mt-6 grid grid-cols-2 gap-2.5">
            <button
                type="button"
                @click="$store.confirm.accept()"
                :disabled="$store.confirm.busy"
                :class="$store.confirm.tone === 'danger' ? 'btn-danger cursor-pointer' : 'btn-primary cursor-pointer'"
            >
                <svg x-show="$store.confirm.busy" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                <span x-text="$store.confirm.confirmLabel"></span>
            </button>

            <button
                type="button"
                x-ref="cancel"
                @click="$store.confirm.cancel()"
                :disabled="$store.confirm.busy"
                class="btn-secondary cursor-pointer"
                x-text="$store.confirm.cancelLabel"
            ></button>
        </div>
    </div>
</div>
