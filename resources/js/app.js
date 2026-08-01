import './bootstrap';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

const storedTheme = window.localStorage.getItem('peoplehub-theme');
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
        window.localStorage.setItem('peoplehub-theme', this.dark ? 'dark' : 'light');
    },
});

window.Alpine = Alpine;
Alpine.start();
