<header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-line bg-surface/80 px-4 backdrop-blur sm:px-6 lg:px-8">
    {{-- منو: دسکتاپ جمع/باز، موبایل کشو --}}
    <button
        type="button"
        @click="$store.sidebar.toggle()"
        class="flex size-10 shrink-0 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg"
        aria-label="منو"
    >
        <x-ui.icon name="menu" class="size-5" />
    </button>

    {{-- جستجو (فعلاً رویداد open-search را می‌فرستد؛ بعداً به جستجوی سراسری وصل می‌شود) --}}
    <button
        type="button"
        @click="$dispatch('open-search')"
        @keydown.window.prevent.ctrl.k="$dispatch('open-search')"
        @keydown.window.prevent.meta.k="$dispatch('open-search')"
        class="flex h-10 w-full max-w-md items-center gap-2.5 rounded-control border border-line bg-canvas px-3.5 text-sm text-muted transition hover:border-primary/40 hover:text-fg max-sm:size-10 max-sm:w-10 max-sm:justify-center max-sm:px-0"
        aria-label="جستجو"
    >
        <x-ui.icon name="search" class="size-4.5" />
        <span class="max-sm:hidden">جستجو در پروژه‌ها و تسک‌ها…</span>
        <kbd dir="ltr" class="ms-auto rounded border border-line bg-surface px-1.5 py-0.5 font-sans text-[0.6875rem] leading-none max-md:hidden">Ctrl K</kbd>
    </button>

    <div class="ms-auto flex items-center gap-1.5">
        {{-- تسک جدید (فقط با دسترسی نوشتن) --}}
        @can('tasks.write')
            <button type="button" @click="$dispatch('open-task-form')" class="btn-primary h-9! px-3.5! text-sm!">
                <x-ui.icon name="plus" class="size-4" />
                <span class="max-sm:hidden">تسک جدید</span>
                <span class="sr-only sm:hidden">تسک جدید</span>
            </button>
        @endcan

        <x-ui.theme-toggle />

        <livewire:notification-bell />

        {{-- منوی کاربر --}}
        <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button
                type="button"
                @click="open = !open"
                :aria-expanded="open"
                aria-haspopup="menu"
                class="flex items-center gap-2.5 rounded-control p-1 transition hover:bg-line/50 md:pe-2"
            >
                <x-ui.avatar :user="auth()->user()" :size="36" />
                <div class="hidden text-start leading-tight md:block">
                    <div class="text-sm font-medium">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-muted">{{ auth()->user()->roleLabel() }}</div>
                </div>
                <x-ui.icon name="chevron-down" class="hidden size-4 text-muted md:block" />
            </button>

            <div
                x-show="open"
                x-cloak
                @click.outside="open = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="panel absolute end-0 top-full z-50 mt-2 w-60 origin-top-end p-1.5"
                role="menu"
            >
                <div class="border-b border-line px-3 pb-2.5 pt-2">
                    <div class="truncate text-sm font-medium">{{ auth()->user()->name }}</div>
                    <div dir="ltr" class="truncate text-start text-xs text-muted">{{ auth()->user()->email }}</div>
                </div>

                <div class="space-y-0.5 pt-1.5">
                    <a
                        href="{{ route('settings.profile') }}"
                        wire:navigate
                        role="menuitem"
                        class="flex h-9 items-center gap-2.5 rounded-control px-3 text-sm transition hover:bg-line/50"
                    >
                        <x-ui.icon name="user" class="size-4.5 text-muted" />
                        پروفایل من
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            role="menuitem"
                            class="flex h-9 w-full items-center gap-2.5 rounded-control px-3 text-sm text-danger transition hover:bg-danger/10"
                        >
                            <x-ui.icon name="logout" class="size-4.5" />
                            خروج
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
