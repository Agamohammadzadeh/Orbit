<div
    x-data="{
        items: [],
        add(detail) {
            const message = detail?.message ?? detail?.[0]?.message;
            if (!message) return;
            const id = Date.now() + Math.random();
            this.items.push({ id, message, tone: detail?.tone ?? detail?.[0]?.tone ?? 'success' });
            setTimeout(() => this.items = this.items.filter((i) => i.id !== id), 4000);
        },
    }"
    @toast.window="add($event.detail)"
    class="pointer-events-none fixed bottom-4 start-4 z-[70] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"
    aria-live="polite"
>
    <template x-for="t in items" :key="t.id">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            :class="t.tone === 'error' ? 'border-danger/30 text-danger' : 'border-success/30 text-success'"
            class="pointer-events-auto flex items-center gap-2.5 rounded-control border bg-surface px-3.5 py-3 text-sm shadow-pop"
            role="status"
        >
            <x-ui.icon name="check-circle" class="size-4.5 shrink-0" x-show="t.tone !== 'error'" />
            <x-ui.icon name="alert-triangle" class="size-4.5 shrink-0" x-show="t.tone === 'error'" />
            <span class="text-fg" x-text="t.message"></span>
        </div>
    </template>
</div>
