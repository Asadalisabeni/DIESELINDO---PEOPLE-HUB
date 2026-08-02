<div
    x-data="{ visible: false, timer: null }"
    x-on:peoplehub-toast.window="visible = true; clearTimeout(timer); timer = setTimeout(() => visible = false, 4500)"
    class="pointer-events-none fixed inset-x-4 bottom-4 z-[60] flex justify-end sm:inset-x-6 sm:bottom-6"
    aria-live="polite"
    aria-atomic="true"
>
    <div
        x-show="visible"
        x-cloak
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-y-3 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        class="surface-panel pointer-events-auto flex w-full max-w-sm gap-3 rounded-xl border p-4 shadow-overlay"
        role="status"
    >
        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
            <x-icon name="check" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-primary">{{ __('ui.design.toast_title') }}</p>
            <p class="mt-1 text-sm leading-5 text-secondary">{{ __('ui.design.toast_body') }}</p>
        </div>
        <button type="button" x-on:click="visible = false" class="-m-1 rounded-lg p-1 text-secondary hover:text-primary" aria-label="{{ __('ui.actions.close') }}">
            <x-icon name="x" size="4" />
        </button>
    </div>
</div>
