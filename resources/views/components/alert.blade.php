@props(['variant' => 'info', 'title'])

@php
    [$wrapper, $icon] = match ($variant) {
        'success' => ['border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100', 'check'],
        'warning' => ['border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100', 'warning'],
        'danger' => ['border-red-200 bg-red-50 text-red-950 dark:border-red-800 dark:bg-red-950/40 dark:text-red-100', 'error'],
        default => ['border-blue-200 bg-blue-50 text-blue-950 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100', 'info'],
    };
@endphp

<div role="{{ $variant === 'danger' ? 'alert' : 'status' }}" {{ $attributes->class(['flex gap-3 rounded-xl border p-4', $wrapper]) }}>
    <x-icon :name="$icon" class="mt-0.5" />
    <div class="min-w-0">
        <p class="text-sm font-bold">{{ $title }}</p>
        <div class="mt-1 text-sm leading-6 opacity-80">{{ $slot }}</div>
    </div>
</div>
