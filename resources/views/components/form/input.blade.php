@props([
    'name',
    'label',
    'type' => 'text',
    'hint' => null,
    'error' => null,
])

@php
    $descriptionId = $error ? $name.'-error' : ($hint ? $name.'-hint' : null);
@endphp

<div>
    <label for="{{ $name }}" class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($descriptionId) aria-describedby="{{ $descriptionId }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->class(['control mt-2', 'border-red-500 focus:border-red-600 focus:ring-red-100 dark:border-red-500 dark:focus:ring-red-950' => $error]) }}
    >
    @if ($error)
        <p id="{{ $name }}-error" class="mt-2 flex items-center gap-1.5 text-sm font-medium text-red-700 dark:text-red-300">
            <x-icon name="error" size="4" />{{ $error }}
        </p>
    @elseif ($hint)
        <p id="{{ $name }}-hint" class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
</div>
