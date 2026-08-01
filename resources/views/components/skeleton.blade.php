@props(['lines' => 3])

<div role="status" aria-label="{{ __('ui.design.loading_title') }}" {{ $attributes->class(['animate-pulse space-y-3']) }}>
    <div class="h-4 w-2/5 rounded-full bg-slate-200 dark:bg-slate-700"></div>
    @for ($line = 0; $line < $lines; $line++)
        <div @class(['h-3 rounded-full bg-slate-200 dark:bg-slate-700', 'w-full' => $line < $lines - 1, 'w-4/5' => $line === $lines - 1])></div>
    @endfor
    <span class="sr-only">{{ __('ui.design.loading_body') }}</span>
</div>
