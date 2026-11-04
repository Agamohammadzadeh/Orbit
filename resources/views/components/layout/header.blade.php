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
            @php
                $routeProject = request()->route('project');
                $routeTask = request()->route('task');
                $headerProjectId = $routeProject instanceof \App\Models\Project
                    ? $routeProject->id
                    : ($routeTask instanceof \App\Models\Task ? $routeTask->project_id : null);
            @endphp

            <button
                type="button"
                @click="Livewire.dispatch('open-task-form', { projectId: @js($headerProjectId) })"
                class="btn-primary h-9! px-3.5! text-sm!"
            >
                <x-ui.icon name="plus" class="size-4" />
                <span class="max-sm:hidden">تسک جدید</span>
                <span class="sr-only sm:hidden">تسک جدید</span>
            </button>
        @endcan

        <x-ui.theme-toggle />

        <livewire:notification-bell />

        {{-- منوی کاربر --}}
        <x-ui.dropdown width="w-60">
            <x-slot:trigger>
                <button
                    type="button"
                    @click="toggle()"
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
            </x-slot:trigger>

            <div class="border-b border-line px-3 pb-2.5 pt-2">
                <div class="truncate text-sm font-medium">{{ auth()->user()->name }}</div>
                <div dir="ltr" class="truncate text-start text-xs text-muted">{{ auth()->user()->email }}</div>
            </div>

            <div class="space-y-0.5 pt-1.5">
                <x-ui.dropdown-item :href="route('settings.profile')" icon="user">پروفایل من</x-ui.dropdown-item>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.dropdown-item type="submit" icon="logout" tone="danger">خروج</x-ui.dropdown-item>
                </form>
            </div>
        </x-ui.dropdown>
    </div>
</header>
