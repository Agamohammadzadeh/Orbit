@props(['align' => 'end', 'width' => 'w-60'])

<div
    {{ $attributes->class('relative') }}
    x-data="{
        open: false,
        up: false,
        toggle() {
            if (this.open) return this.close();
            const r = this.$el.getBoundingClientRect();
            this.up = innerHeight - r.bottom < 320 && r.top > innerHeight - r.bottom;
            this.open = true;
        },
        close() {
            this.open = false;
        },
        focusItem(step) {
            const items = [...this.$refs.panel.querySelectorAll('[role=menuitem]:not([disabled])')];
            if (!items.length) return;
            const i = items.indexOf(document.activeElement);
            items[(i + step + items.length) % items.length].focus();
        },
    }"
    @click.outside="close()"
    @keydown.escape.window="open && close()"
>
    {{ $trigger }}

    <div
        x-ref="panel"
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        :class="up ? 'bottom-full mb-2 origin-bottom' : 'top-full mt-2 origin-top'"
        class="panel absolute z-50 p-1.5 {{ $width }} {{ $align === 'start' ? 'start-0' : 'end-0' }}"
        role="menu"
        @click="$event.target.closest('[role=menuitem]') && close()"
        @keydown.arrow-down.prevent="focusItem(1)"
        @keydown.arrow-up.prevent="focusItem(-1)"
    >
        {{ $slot }}
    </div>
</div>
