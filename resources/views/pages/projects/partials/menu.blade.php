<div class="relative z-10">
    <x-ui.dropdown width="w-52">
        <x-slot:trigger>
            <button
                type="button"
                @click="toggle()"
                :aria-expanded="open"
                aria-haspopup="menu"
                class="flex size-8 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg"
                aria-label="عملیات {{ $p->name }}"
            >
                <x-ui.icon name="more" class="size-5" />
            </button>
        </x-slot:trigger>

        <x-ui.dropdown-item :href="route('projects.show', $p)" icon="folder">باز کردن</x-ui.dropdown-item>

        @can('update', $p)
            <x-ui.dropdown-item :href="route('projects.edit', $p)" icon="edit">ویرایش</x-ui.dropdown-item>
        @endcan

        @can('archive', $p)
            @if ($archivedView)
                <x-ui.dropdown-item icon="restore" wire:click="restore({{ $p->id }})">بازگردانی</x-ui.dropdown-item>
            @else
                <button
                    type="button"
                    role="menuitem"
                    @click="$store.confirm.ask({
                        title: @js('آرشیو «'.$p->name.'»'),
                        message: 'پروژه از فهرست فعال و جستجو خارج می‌شود. تسک‌ها و اعضا حفظ می‌شوند و هر وقت بخواهی بازش می‌گردانی.',
                        confirmLabel: 'آرشیو کن',
                        tone: 'primary',
                        action: () => $wire.archive({{ $p->id }}),
                    })"
                    class="flex h-9 w-full items-center gap-2.5 rounded-control px-3 text-start text-sm transition hover:bg-line/50 focus:outline-none focus-visible:bg-line/50"
                >
                    <x-ui.icon name="archive" class="size-4.5 text-muted" />
                    آرشیو
                </button>
            @endif
        @endcan

        @can('delete' , $p)
            <button
                type="button"
                role="menuitem"
                @click="$store.confirm.ask({
                        title: @js('حذف «'.$p->name.'»'),
                        message: 'پروژه و دسترسی های آن به طور کامل حذف می‌شود و امکان بازگردانی آن وجود نخواهد داشت.',
                        confirmLabel: 'حذف کن',
                        tone: 'danger',
                        action: () => $wire.delete({{ $p->id }}),
                    })"
                class="flex h-9 w-full items-center gap-2.5 rounded-control px-3 text-start text-sm transition hover:bg-red-500/10 hover:text-prio-urgent hover:[&>svg]:stroke-prio-urgent focus:outline-none focus-visible:bg-line/50 "
            >
                <x-ui.icon name="trash" class="size-4.5 text-muted" />
                حذف
            </button>
        @endcan
    </x-ui.dropdown>
</div>
