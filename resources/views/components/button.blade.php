@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
    'disabled' => false,
])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-slate-300 bg-white text-slate-800 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-600 dark:bg-navy-900 dark:text-slate-100 dark:hover:bg-navy-800',
        'ghost' => 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-white/10',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-700 focus-visible:outline-red-600 dark:bg-red-500 dark:hover:bg-red-400',
        default => 'bg-brand-600 text-white shadow-sm shadow-brand-900/10 hover:bg-brand-700 focus-visible:outline-brand-600 dark:bg-brand-500 dark:text-navy-950 dark:hover:bg-brand-400',
    };

    $base = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-bold transition disabled:cursor-not-allowed disabled:opacity-50';
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class([$base, $classes]) }}>{{ $slot }}</a>
@elseif ($href)
    <span aria-disabled="true" {{ $attributes->class([$base, $classes, 'cursor-not-allowed opacity-50']) }}>{{ $slot }}</span>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->class([$base, $classes]) }}>{{ $slot }}</button>
@endif
