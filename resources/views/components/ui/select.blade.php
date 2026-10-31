@props([
    'options' => [],
    'placeholder' => 'انتخاب کن',
    'id' => null,
    'label' => null,
    'disabled' => false,
])

@php
    $modelAttr = collect($attributes->getAttributes())->keys()->first(fn ($k) => str_starts_with($k, 'wire:model'));
    $model = $modelAttr ? $attributes->get($modelAttr) : null;
    $live = $modelAttr && str_contains($modelAttr, '.live');

    $list = collect($options)
        ->map(fn ($text, $value) => ['value' => (string) $value, 'label' => $text])
        ->values();
@endphp

<div
    {{ $attributes->whereDoesntStartWith('wire:model')->class('relative') }}
    x-data="{
        open: false,
        up: false,
        active: -1,
        model: @js($model),
        live: @js((bool) $live),
        disabled: @js((bool) $disabled),
        options: @js($list),
        get value() {
            return String(this.$wire[this.model] ?? '');
        },
        get current() {
            return this.options.find((o) => o.value === this.value);
        },
        toggle() {
            if (this.disabled) return;
            if (this.open) return this.close();
            const r = this.$el.getBoundingClientRect();
            this.up = innerHeight - r.bottom < 280 && r.top > innerHeight - r.bottom;
            this.active = Math.max(0, this.options.findIndex((o) => o.value === this.value));
            this.open = true;
            this.$nextTick(() => this.scrollActive());
        },
        close() {
            this.open = false;
        },
        pick(i) {
            const o = this.options[i];
            if (!o) return;
            this.$wire.$set(this.model, o.value, this.live);
            this.close();
            this.$refs.button.focus();
        },
        move(step) {
            if (!this.open) return this.toggle();
            const n = this.options.length;
            this.active = (this.active + step + n) % n;
            this.$nextTick(() => this.scrollActive());
        },
        scrollActive() {
            this.$refs.panel?.children[this.active]?.scrollIntoView({ block: 'nearest' });
        },
    }"
    @click.outside="close()"
    @keydown.escape.window="open && close()"
>
    <button
        type="button"
        x-ref="button"
        @if ($id) id="{{ $id }}" @endif
        @if ($label) aria-label="{{ $label }}" @endif
        @disabled($disabled)
        @click="toggle()"
        @keydown.arrow-down.prevent="move(1)"
        @keydown.arrow-up.prevent="move(-1)"
        @keydown.enter.prevent="open ? pick(active) : toggle()"
        :aria-expanded="open"
        aria-haspopup="listbox"
        class="input text-start disabled:cursor-not-allowed disabled:opacity-70"
    >
        <span class="flex w-full items-center justify-between gap-2">
            <span
                class="truncate"
                :class="current ? '' : 'text-muted/70'"
                x-text="current ? current.label : @js($placeholder)"
            ></span>
            <x-ui.icon name="chevron-down" class="size-4 shrink-0 text-muted transition" ::class="open && 'rotate-180'" />
        </span>
    </button>

    <div
        x-ref="panel"
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        :class="up ? 'bottom-full mb-1.5 origin-bottom' : 'top-full mt-1.5 origin-top'"
        class="panel absolute inset-x-0 z-40 max-h-64 min-w-max overflow-y-auto p-1.5"
        role="listbox"
    >
        @foreach ($list as $i => $option)
            <button
                type="button"
                role="option"
                :aria-selected="value === @js($option['value'])"
                @click="pick({{ $i }})"
                @mouseenter="active = {{ $i }}"
                :class="[
                    active === {{ $i }} ? 'bg-line/50' : '',
                    value === @js($option['value']) ? 'font-medium text-primary' : '',
                ]"
                class="flex h-9 w-full items-center justify-between gap-3 rounded-control px-3 text-start text-sm transition"
            >
                <span class="truncate">{{ $option['label'] }}</span>
                <span x-show="value === @js($option['value'])" class="shrink-0 text-primary">
                    <x-ui.icon name="check" class="size-4" />
                </span>
            </button>
        @endforeach
    </div>
</div>
