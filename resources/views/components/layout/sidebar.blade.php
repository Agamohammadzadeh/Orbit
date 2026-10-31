@php
    $nav = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'home', 'label' => 'داشبورد'],
        ['route' => 'projects.index', 'match' => 'projects.*', 'icon' => 'folder', 'label' => 'پروژه‌ها'],
        ['route' => 'calendar', 'match' => 'calendar', 'icon' => 'calendar', 'label' => 'تقویم'],
        ['route' => 'reports', 'match' => 'reports', 'icon' => 'chart', 'label' => 'گزارش‌ها'],
    ];
@endphp

<aside
    class="fixed inset-y-0 start-0 z-40 flex w-64 flex-col border-e border-line bg-surface transition-[transform,width] duration-200 max-lg:ltr:-translate-x-full max-lg:rtl:translate-x-full lg:w-(--sidebar-w)"
    :class="{ 'max-lg:translate-x-0!': $store.sidebar.mobileOpen }"
    aria-label="منوی اصلی"
>
    {{-- لوگو --}}
    <div class="flex h-16 shrink-0 items-center border-b border-line px-5 lg:sb-collapsed:justify-center lg:sb-collapsed:px-0">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center" aria-label="Orbit">
            <div class="lg:sb-collapsed:hidden">
                <x-ui.logo size="30" />
            </div>
            <div class="hidden lg:sb-collapsed:block">
                <x-ui.logo variant="mark" size="30" />
            </div>
        </a>

        {{-- بستن کشو (فقط موبایل) --}}
        <button
            type="button"
            @click="$store.sidebar.closeMobile()"
            class="ms-auto flex size-9 items-center justify-center rounded-control text-muted transition hover:bg-line/50 hover:text-fg lg:hidden"
            aria-label="بستن منو"
        >
            <x-ui.icon name="x" class="size-5" />
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
        {{-- منوی اصلی --}}
        <ul class="space-y-1">
            @foreach ($nav as $item)
                <li>
                    <x-ui.nav-item
{{--                        :href="route($item['route'])"--}}
                        href="#"
                        :icon="$item['icon']"
                        :label="$item['label']"
                        :active="request()->routeIs($item['match'])"
                    />
                </li>
            @endforeach
        </ul>

        {{-- پروژه‌های اخیر --}}
        <livewire:sidebar-projects />

        {{-- مدیریت (فقط با دسترسی) --}}
        @canany(['users.manage', 'viewAny'], [\App\Models\Client::class])
            @php
                $canClients = auth()->user()->can('viewAny', \App\Models\Client::class);
                $canUsers = auth()->user()->can('users.manage');
            @endphp

            @if ($canClients || $canUsers)
                <div>
                    <p class="mb-2 px-3 text-xs font-medium text-muted lg:sb-collapsed:hidden">مدیریت</p>
                    <div class="mx-2 mb-2 hidden h-px bg-line lg:sb-collapsed:block"></div>

                    <ul class="space-y-1">
                        @if ($canClients)
                            <li>
                                <x-ui.nav-item
{{--                                    :href="route('clients')" --}}
                                    href="#"
                                    icon="briefcase"
                                    label="مشتری‌ها"
                                    :active="request()->routeIs('clients')"
                                />
                            </li>
                        @endif
                        @if ($canUsers)
                            <li>
                                <x-ui.nav-item
{{--                                    :href="route('team')" --}}
                                    href="#"
                                    icon="users"
                                    label="اعضای تیم"
                                    :active="request()->routeIs('team')"
                                />
                            </li>
                            <li>
                                <x-ui.nav-item
{{--                                    :href="route('team.roles')" --}}
                                    href="#"
                                    icon="shield"
                                    label="نقش‌ها و دسترسی‌ها"
                                    :active="request()->routeIs('team.roles')"
                                />
                            </li>
                        @endif
                    </ul>
                </div>
            @endif
        @endcanany
    </nav>
</aside>
