@props(['id' => null, 'placeholder' => 'انتخاب تاریخ', 'clearable' => true, 'disabled' => false])

@php
    $modelAttr = collect($attributes->getAttributes())->keys()->first(fn ($k) => str_starts_with($k, 'wire:model'));
    $model = $modelAttr ? $attributes->get($modelAttr) : null;
    $live = $modelAttr && str_contains($modelAttr, '.live');
@endphp

<div
    {{ $attributes->whereDoesntStartWith('wire:model')->class('relative') }}
    x-data="{
        open: false,
        up: false,
        vy: 0,
        vm: 1,
        model: @js($model),
        live: @js((bool) $live),
        disabled: @js((bool) $disabled),
        months: ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'],
        days: ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'],
        fmt: new Intl.DateTimeFormat('en-u-ca-persian-nu-latn', { year: 'numeric', month: 'numeric', day: 'numeric', timeZone: 'UTC' }),

        get value() {
            return this.$wire[this.model] || '';
        },
        get text() {
            if (!this.value) return '';
            const j = this.toJ(new Date(this.value + 'T00:00:00Z'));
            return j.y + '/' + this.pad(j.m) + '/' + this.pad(j.d);
        },
        get cells() {
            const first = this.toG(this.vy, this.vm, 1);
            const next = this.vm === 12 ? this.toG(this.vy + 1, 1, 1) : this.toG(this.vy, this.vm + 1, 1);
            const len = Math.round((next - first) / 86400000);
            const cells = Array((first.getUTCDay() + 1) % 7).fill(null);
            for (let d = 1; d <= len; d++) {
                cells.push({ d, iso: this.iso(new Date(first.getTime() + (d - 1) * 86400000)) });
            }
            return cells;
        },

        pad(n) {
            return String(n).padStart(2, '0');
        },
        iso(d) {
            return d.toISOString().slice(0, 10);
        },
        todayIso() {
            const t = new Date();
            return t.getFullYear() + '-' + this.pad(t.getMonth() + 1) + '-' + this.pad(t.getDate());
        },
        // تاریخ میلادی (UTC) به شمسی
        toJ(d) {
            const p = Object.fromEntries(this.fmt.formatToParts(d).map((x) => [x.type, parseInt(x.value, 10)]));
            return { y: p.year, m: p.month, d: p.day };
        },
        cmp(a, b) {
            return (a.y - b.y) || (a.m - b.m) || (a.d - b.d);
        },
        // شمسی به میلادی: حدس اولیه، بعد اصلاح روز به روز تا تاریخ شمسی برابر شود
        toG(y, m, d) {
            const offset = m <= 6 ? (m - 1) * 31 : 186 + (m - 7) * 30;
            let g = new Date(Date.UTC(y + 621, 2, 21 + offset + d - 1));
            for (let i = 0; i < 10; i++) {
                const c = this.cmp(this.toJ(g), { y, m, d });
                if (c === 0) break;
                g = new Date(g.getTime() + (c > 0 ? -86400000 : 86400000));
            }
            return g;
        },

        toggle() {
            if (this.disabled) return;
            if (this.open) return this.close();
            const r = this.$el.getBoundingClientRect();
            this.up = innerHeight - r.bottom < 380 && r.top > innerHeight - r.bottom;
            const j = this.toJ(new Date((this.value || this.todayIso()) + 'T00:00:00Z'));
            this.vy = j.y;
            this.vm = j.m;
            this.open = true;
        },
        close() {
            this.open = false;
        },
        shift(n) {
            let m = this.vm + n;
            let y = this.vy;
            if (m > 12) { m = 1; y++; }
            if (m < 1) { m = 12; y--; }
            this.vm = m;
            this.vy = y;
        },
        pick(iso) {
            this.$wire.$set(this.model, iso, this.live);
            this.close();
        },
    }"
    @click.outside="close()"
    @keydown.escape.window="open && close()"
>
    <button
        type="button"
        @if ($id) id="{{ $id }}" @endif
        @disabled($disabled)
        @click="toggle()"
        :aria-expanded="open"
        aria-haspopup="dialog"
        class="input text-start disabled:cursor-not-allowed disabled:opacity-70"
    >
        <span class="flex w-full items-center justify-between gap-2">
            <span
                dir="ltr"
                class="tabular truncate"
                :class="text ? '' : 'text-muted/70'"
                x-text="text || @js($placeholder)"
            ></span>
            <x-ui.icon name="calendar" class="size-4.5 shrink-0 text-muted" />
        </span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        :class="up ? 'bottom-full mb-1.5 origin-bottom' : 'top-full mt-1.5 origin-top'"
        class="panel absolute start-0 z-40 w-72 p-3"
        role="dialog"
        aria-label="انتخاب تاریخ"
    >
        {{-- ماه و سال --}}
        <div class="flex items-center justify-between">
            <button type="button" @click="shift(-1)" class="flex size-8 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg" aria-label="ماه قبل">
                <x-ui.icon name="chevron-left" class="size-4.5 rtl:rotate-180" />
            </button>
            <span class="text-sm font-bold" x-text="months[vm - 1] + ' ' + vy"></span>
            <button type="button" @click="shift(1)" class="flex size-8 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg" aria-label="ماه بعد">
                <x-ui.icon name="chevron-right" class="size-4.5 rtl:rotate-180" />
            </button>
        </div>

        {{-- روزهای هفته --}}
        <div class="mt-3 grid grid-cols-7 text-center text-xs text-muted">
            <template x-for="d in days" :key="d">
                <span class="py-1" x-text="d"></span>
            </template>
        </div>

        {{-- روزها --}}
        <div class="mt-1 grid grid-cols-7 gap-0.5">
            <template x-for="(c, i) in cells" :key="i">
                <div>
                    <button
                        type="button"
                        x-show="c"
                        @click="pick(c.iso)"
                        class="tabular flex size-9 items-center justify-center rounded-control text-sm transition"
                        :class="c && c.iso === value
                            ? 'bg-primary font-bold text-on-primary'
                            : (c && c.iso === todayIso() ? 'font-bold text-primary ring-1 ring-primary/40 hover:bg-line/50' : 'hover:bg-line/50')"
                        x-text="c ? c.d : ''"
                    ></button>
                </div>
            </template>
        </div>

        {{-- پایین --}}
        <div class="mt-3 flex items-center justify-between border-t border-line pt-2.5 text-sm">
            <button type="button" @click="pick(todayIso())" class="text-primary transition hover:underline">امروز</button>
            @if ($clearable)
                <button type="button" x-show="value" @click="pick('')" class="text-muted transition hover:text-danger">پاک کردن</button>
            @endif
        </div>
    </div>
</div>
