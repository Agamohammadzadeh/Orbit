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
