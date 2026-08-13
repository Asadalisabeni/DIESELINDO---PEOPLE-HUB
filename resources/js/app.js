import './bootstrap';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import './attendance-offline';

const readStoredTheme = () => {
    try {
        return window.localStorage.getItem('peoplehub-theme');
    } catch {
        return null;
    }
};

const persistTheme = (theme) => {
    try {
        window.localStorage.setItem('peoplehub-theme', theme);
    } catch {
        // Theme persistence is optional; Alpine must still initialize.
    }
};

const storedTheme = readStoredTheme();
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
const startsDark = storedTheme === 'dark' || (storedTheme === null && prefersDark);

document.documentElement.classList.toggle('dark', startsDark);
document.documentElement.style.colorScheme = startsDark ? 'dark' : 'light';

Alpine.plugin(focus);

Alpine.store('theme', {
    dark: startsDark,

    toggle() {
        this.dark = ! this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        document.documentElement.style.colorScheme = this.dark ? 'dark' : 'light';
        persistTheme(this.dark ? 'dark' : 'light');
    },
});

window.Alpine = Alpine;
Alpine.start();
