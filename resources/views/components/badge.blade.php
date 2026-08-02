@props(['variant' => 'neutral'])

@php
    $classes = match ($variant) {
        'success' => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-400/20',
        'warning' => 'bg-amber-100 text-amber-900 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/20',
        'danger' => 'bg-red-100 text-red-800 ring-red-600/20 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-400/20',
        'brand' => 'bg-brand-100 text-brand-800 ring-brand-600/20 dark:bg-brand-500/15 dark:text-brand-300 dark:ring-brand-400/20',
        default => 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-500/15 dark:text-slate-300 dark:ring-slate-400/20',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset', $classes]) }}>
    {{ $slot }}
</span>
