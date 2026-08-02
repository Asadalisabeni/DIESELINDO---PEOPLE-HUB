@props(['variant' => 'empty', 'title', 'description'])

@php
    [$icon, $iconClass] = match ($variant) {
        'error' => ['error', 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'],
        'denied' => ['lock', 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200'],
        'loading' => ['clock', 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300'],
        default => ['inbox', 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'],
    };
@endphp

<div {{ $attributes->class(['surface-panel rounded-2xl border p-6 text-center']) }}>
    <span class="mx-auto grid size-12 place-items-center rounded-xl {{ $iconClass }}">
        <x-icon :name="$icon" size="6" @class(['animate-pulse' => $variant === 'loading']) />
    </span>
    <h3 class="mt-4 text-base font-bold text-primary">{{ $title }}</h3>
    <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-secondary">{{ $description }}</p>
    @if (trim((string) $slot))
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
