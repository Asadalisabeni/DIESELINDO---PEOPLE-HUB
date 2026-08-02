@props(['name', 'title'])

<div
    x-data="{ open: false }"
    x-on:open-drawer.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $name }}-title"
>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-navy-950/70 backdrop-blur-sm" x-on:click="open = false"></div>
    <aside
        x-show="open"
        x-transition:enter="transition duration-300 ease-out"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition duration-200 ease-in"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        x-trap.inert.noscroll="open"
        class="surface-panel absolute inset-y-0 right-0 w-full max-w-md border-l p-6 shadow-overlay"
    >
        <div class="flex items-start justify-between gap-4">
            <h2 id="{{ $name }}-title" class="text-lg font-bold text-primary">{{ $title }}</h2>
            <button type="button" x-on:click="open = false" class="-m-2 rounded-lg p-2 text-secondary hover:bg-slate-100 hover:text-primary dark:hover:bg-white/10" aria-label="{{ __('ui.actions.close') }}">
                <x-icon name="x" />
            </button>
        </div>
        <div class="mt-5 text-sm leading-6 text-secondary">{{ $slot }}</div>
    </aside>
</div>
