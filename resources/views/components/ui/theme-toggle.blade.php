<button
    type="button"
    @click="$store.theme.set(document.documentElement.classList.contains('dark') ? 'light' : 'dark')"
    {{ $attributes->class('flex size-10 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg') }}
    aria-label="تغییر تم"
>
    <x-ui.icon name="moon" class="size-5 dark:hidden" />
    <x-ui.icon name="sun" class="hidden size-5 dark:block" />
</button>
