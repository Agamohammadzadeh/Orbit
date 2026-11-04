@props(['members' => [], 'placeholder' => '', 'rows' => 3, 'id' => null])

<div
    class="relative"
    x-data="{
        members: @js($members),
        open: false,
        query: '',
        start: -1,
        active: 0,
        get matches() {
            const q = this.query.toLowerCase();
            return this.members.filter((m) => m.name.toLowerCase().includes(q)).slice(0, 6);
        },
        detect() {
            const el = this.$refs.field;
            const pos = el.selectionStart;
            const m = el.value.slice(0, pos).match(/(?:^|\s)@([^\s@]*)$/u);
            if (!m) { this.open = false; return; }
            this.query = m[1];
            this.start = pos - m[1].length - 1;
            this.active = 0;
            this.open = this.matches.length > 0;
        },
        pick(m) {
            if (!m) return;
            const el = this.$refs.field;
            const pos = el.selectionStart;
            el.value = el.value.slice(0, this.start) + '@' + m.name + ' ' + el.value.slice(pos);
            const caret = this.start + m.name.length + 2;
            el.setSelectionRange(caret, caret);
            el.dispatchEvent(new Event('input', { bubbles: true }));
            this.open = false;
            el.focus();
        },
    }"
    @click.outside="open = false"
>
    <textarea
        x-ref="field"
        @if ($id) id="{{ $id }}" @endif
        {{ $attributes->whereStartsWith('wire:model') }}
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        class="input h-auto! resize-y py-2.5"
        @input="detect()"
        @click="detect()"
        @keydown.arrow-down="if (open) { $event.preventDefault(); active = (active + 1) % matches.length }"
        @keydown.arrow-up="if (open) { $event.preventDefault(); active = (active - 1 + matches.length) % matches.length }"
        @keydown.enter="if (open) { $event.preventDefault(); pick(matches[active]) }"
        @keydown.tab="if (open) { $event.preventDefault(); pick(matches[active]) }"
        @keydown.escape="open = false"
        @keydown.ctrl.enter.prevent="$el.form?.requestSubmit()"
    ></textarea>

    <div
        x-show="open"
        x-cloak
        class="panel absolute start-0 top-full z-30 mt-1.5 w-64 max-w-full p-1.5"
        role="listbox"
    >
        <template x-for="(m, i) in matches" :key="m.id">
            <button
                type="button"
                role="option"
                @click="pick(m)"
                @mouseenter="active = i"
                :class="active === i ? 'bg-line/50' : ''"
                class="flex h-9 w-full items-center gap-2 rounded-control px-3 text-start text-sm transition"
            >
                <span class="text-muted">@</span>
                <span class="truncate" x-text="m.name"></span>
            </button>
        </template>
    </div>
</div>
