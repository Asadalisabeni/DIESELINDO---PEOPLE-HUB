@props(['name', 'title'])

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 grid place-items-end p-0 sm:place-items-center sm:p-6"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $name }}-title"
>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-navy-950/70 backdrop-blur-sm" x-on:click="open = false"></div>
    <div
        x-show="open"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-y-6 opacity-0 sm:scale-95 sm:translate-y-0"
        x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
        x-trap.inert.noscroll="open"
        class="surface-panel relative w-full max-w-lg rounded-t-2xl border p-6 shadow-overlay sm:rounded-2xl"
    >
        <div class="flex items-start justify-between gap-4">
            <h2 id="{{ $name }}-title" class="text-lg font-bold text-primary">{{ $title }}</h2>
            <button type="button" x-on:click="open = false" class="-m-2 rounded-lg p-2 text-secondary hover:bg-slate-100 hover:text-primary dark:hover:bg-white/10" aria-label="{{ __('ui.actions.close') }}">
                <x-icon name="x" />
            </button>
        </div>
        <div class="mt-4 text-sm leading-6 text-secondary">{{ $slot }}</div>
        @isset($footer)
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">{{ $footer }}</div>
        @endisset
    </div>
</div>
