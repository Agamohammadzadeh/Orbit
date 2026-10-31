const STORAGE_KEY = 'orbit-theme';

function prefersDark() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

document.addEventListener('alpine:init', () => {
    // استفاده در Blade:  $store.theme.set('dark')  |  'light'  |  'system'
    window.Alpine.store('theme', {
        mode: 'system',

        init() {
            try {
                this.mode = localStorage.getItem(STORAGE_KEY) || 'system';
            } catch (e) {}
            this.apply();

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (this.mode === 'system') this.apply();
            });
        },

        set(mode) {
            this.mode = mode;
            try {
                localStorage.setItem(STORAGE_KEY, mode);
            } catch (e) {}
            this.apply();
        },

        apply() {
            const dark = this.mode === 'dark' || (this.mode === 'system' && prefersDark());
            document.documentElement.classList.toggle('dark', dark);
        },
    });
});

// بعد از هر wire:navigate کلاس dark دوباره اعمال شود
document.addEventListener('livewire:navigated', () => {
    window.Alpine?.store('theme')?.apply();
});


// ---------------- سایدبار ----------------
const SIDEBAR_KEY = 'orbit-sidebar';

document.addEventListener('alpine:init', () => {
    // استفاده در Blade:  $store.sidebar.toggle()  |  $store.sidebar.mobileOpen
    window.Alpine.store('sidebar', {
        collapsed: document.documentElement.classList.contains('sb-collapsed'),
        mobileOpen: false,

        // دسکتاپ: جمع/باز می‌کند و ذخیره می‌کند. موبایل: کشوی منو را باز/بسته می‌کند.
        toggle() {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                this.collapsed = !this.collapsed;
                document.documentElement.classList.toggle('sb-collapsed', this.collapsed);
                try {
                    localStorage.setItem(SIDEBAR_KEY, this.collapsed ? '1' : '0');
                } catch (e) {}
            } else {
                this.mobileOpen = !this.mobileOpen;
            }
        },

        closeMobile() {
            this.mobileOpen = false;
        },
    });
});

// بعد از هر wire:navigate: کشوی موبایل بسته شود و حالت جمع‌شده حفظ بماند
document.addEventListener('livewire:navigated', () => {
    const store = window.Alpine?.store('sidebar');
    if (!store) return;
    store.closeMobile();
    document.documentElement.classList.toggle('sb-collapsed', store.collapsed);
});



// ---------------- دیالوگ تأیید سراسری ----------------
// استفاده در Blade (داخل هر کامپوننت Livewire):
//   @click="$store.confirm.ask({
//       title: 'حذف تسک',
//       message: 'این کار قابل بازگشت نیست.',
//       confirmLabel: 'حذف',
//       tone: 'danger',                       // danger | primary
//       action: () => $wire.deleteTask(),     // تابعی که بعد از تأیید اجرا می‌شود
//   })"
document.addEventListener('alpine:init', () => {
    window.Alpine.store('confirm', {
        open: false,
        busy: false,
        title: '',
        message: '',
        confirmLabel: 'تأیید',
        cancelLabel: 'انصراف',
        tone: 'danger',
        _action: null,

        ask({ title, message = '', confirmLabel = 'تأیید', cancelLabel = 'انصراف', tone = 'danger', action }) {
            Object.assign(this, {
                title,
                message,
                confirmLabel,
                cancelLabel,
                tone,
                _action: action,
                busy: false,
                open: true,
            });
        },

        // تا تمام شدن عملیات (مثلاً درخواست Livewire) دیالوگ باز می‌ماند و دکمه spinner دارد
        async accept() {
            if (this.busy) return;
            this.busy = true;

            try {
                await this._action?.();
            } finally {
                this.busy = false;
                this.open = false;
                this._action = null;
            }
        },

        cancel() {
            if (this.busy) return;
            this.open = false;
            this._action = null;
        },
    });
});
