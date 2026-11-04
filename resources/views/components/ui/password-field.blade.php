@props(['id', 'label', 'error' => null, 'hint' => null])

@php
    $model = collect($attributes->whereStartsWith('wire:model')->getAttributes())->first();
@endphp

<div
    class="space-y-1.5"
    x-data="{
        show: false,
        copied: false,
        generate() {
            const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            const bytes = crypto.getRandomValues(new Uint32Array(12));
            let out = Array.from(bytes, (b) => chars[b % chars.length]).join('');
            if (!/\d/.test(out)) out = out.slice(0, -1) + '7';
            if (!/[a-zA-Z]/.test(out)) out = 'k' + out.slice(1);
            $wire.set(@js($model), out);
            this.show = true;
        },
        async copy() {
            const value = $wire.get(@js($model));
            if (!value) return;
            await navigator.clipboard.writeText(value);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        },
    }"
>
    <label for="{{ $id }}" class="text-sm font-medium">{{ $label }}</label>

    <div class="flex gap-2">
        <div class="relative min-w-0 flex-1">
            <input
                id="{{ $id }}"
                :type="show ? 'text' : 'password'"
                {{ $attributes->whereStartsWith('wire:model') }}
                dir="ltr"
                class="input pe-11! text-start"
                autocomplete="new-password"
                aria-invalid="{{ $error ? 'true' : 'false' }}"
            />
            <button
                type="button"
                @click="show = !show"
                class="absolute inset-y-0 start-0 flex w-11 items-center justify-center text-muted transition hover:text-fg"
                :aria-label="show ? 'پنهان کردن رمز' : 'نمایش رمز'"
            >
                <x-ui.icon name="eye" class="size-5" x-show="!show" />
                <x-ui.icon name="eye-off" class="size-5" x-show="show" x-cloak />
            </button>
        </div>

        <button type="button" @click="generate()" class="btn-secondary w-11 px-0!" title="تولید رمز تصادفی" aria-label="تولید رمز تصادفی">
            <x-ui.icon name="refresh" class="size-4.5" />
        </button>

        <button type="button" @click="copy()" class="btn-secondary w-11 px-0!" title="کپی رمز" aria-label="کپی رمز">
            <x-ui.icon name="check" class="size-4.5 text-success" x-show="copied" x-cloak />
            <x-ui.icon name="copy" class="size-4.5" x-show="!copied" />
        </button>
    </div>

    @if ($error)
        <p class="text-sm text-danger">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
