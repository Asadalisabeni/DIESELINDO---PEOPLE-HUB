@props(['name', 'label'])

<div>
    <label for="{{ $name }}" class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['control mt-2']) }}>
        {{ $slot }}
    </select>
</div>
