/**
 * Alpine behaviour for the UI component library (resources/views/components/ui).
 * Storage access is wrapped: private windows and blocked storage must not break the page.
 */

const store = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // Preference simply isn't remembered.
        }
    },
};

export function registerUi(Alpine) {
    Alpine.store('toasts', {
        items: [],
        push(message, tone = 'success') {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, tone });
            setTimeout(() => this.dismiss(id), 4000);
        },
        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    // Any code (including Livewire) can raise a toast: $dispatch('toast', { message, tone }).
    window.addEventListener('toast', (event) => {
        Alpine.store('toasts').push(event.detail.message, event.detail.tone);
    });

    Alpine.data('shell', () => ({
        collapsed: false,
        drawer: false,
        init() {
            const wide = window.matchMedia('(min-width: 768px)');
            const pref = store.get('tm-sidebar');
            this.collapsed = wide.matches && (pref === 'collapsed' || (pref === null && window.innerWidth < 1100));
            wide.addEventListener('change', (e) => {
                if (!e.matches) this.collapsed = false;
                this.drawer = false;
            });
        },
        toggleCollapsed() {
            this.collapsed = !this.collapsed;
            store.set('tm-sidebar', this.collapsed ? 'collapsed' : 'open');
        },
    }));

    Alpine.data('themeSwitcher', () => ({
        theme: document.documentElement.dataset.theme || 'dark',
        set(theme) {
            this.theme = theme;
            document.documentElement.dataset.theme = theme;
            store.set('tm-theme', theme);
        },
        toggle() {
            const dark =
                this.theme === 'dark' ||
                (this.theme === 'system' && !window.matchMedia('(prefers-color-scheme: light)').matches);
            this.set(dark ? 'light' : 'dark');
        },
    }));

    Alpine.data('copyText', (text, copiedLabel) => ({
        copied: false,
        async copy() {
            try {
                await navigator.clipboard.writeText(text);
                this.copied = true;
                setTimeout(() => (this.copied = false), 1600);
                Alpine.store('toasts').push(copiedLabel);
            } catch {
                const range = document.createRange();
                range.selectNodeContents(this.$refs.text);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
            }
        },
    }));

    Alpine.data('otpInput', (length) => ({
        digits: Array(length).fill(''),
        get value() {
            return this.digits.join('');
        },
        input(index, event) {
            const digit = event.target.value.replace(/\D/g, '').slice(-1);
            this.digits[index] = digit;
            event.target.value = digit;
            if (digit && index < length - 1) this.$refs[`d${index + 1}`].focus();
        },
        back(index, event) {
            if (event.target.value === '' && index > 0) this.$refs[`d${index - 1}`].focus();
        },
        paste(event) {
            const text = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, length);
            if (!text) return;
            event.preventDefault();
            text.split('').forEach((d, i) => {
                this.digits[i] = d;
                this.$refs[`d${i}`].value = d;
            });
            this.$refs[`d${Math.min(text.length, length - 1)}`].focus();
        },
    }));

    Alpine.data('countdown', (until) => ({
        parts: { d: 0, h: 0, m: 0, s: 0 },
        done: false,
        timer: null,
        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },
        destroy() {
            clearInterval(this.timer);
        },
        tick() {
            const left = Math.max(0, Math.floor((new Date(until).getTime() - Date.now()) / 1000));
            this.done = left === 0;
            this.parts = {
                d: Math.floor(left / 86400),
                h: Math.floor((left % 86400) / 3600),
                m: Math.floor((left % 3600) / 60),
                s: left % 60,
            };
            if (this.done) clearInterval(this.timer);
        },
        pad(n) {
            return String(n).padStart(2, '0');
        },
    }));
}
