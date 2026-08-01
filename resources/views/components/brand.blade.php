@props(['compact' => false])

<span {{ $attributes->class(['inline-flex items-center gap-3']) }}>
    <span class="relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-navy-950 text-white shadow-lg shadow-navy-950/15 dark:bg-brand-500 dark:text-navy-950">
        <svg aria-hidden="true" viewBox="0 0 40 40" class="size-10">
            <path fill="currentColor" fill-opacity=".12" d="M0 0h40v40H0z" />
            <path fill="currentColor" d="M9 10h10.3c7.4 0 11.7 3.6 11.7 10s-4.3 10-11.7 10H9V10Zm6 5v10h4.1c3.8 0 5.8-1.7 5.8-5s-2-5-5.8-5H15Z" />
            <path fill="#f97316" d="M7 7h4v4H7zM29 29h4v4h-4z" />
        </svg>
    </span>

    @unless ($compact)
        <span class="min-w-0 leading-none">
            <span class="block truncate text-[15px] font-bold tracking-tight text-white">Dieselindo</span>
            <span class="mt-1 block truncate text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">PeopleHub</span>
        </span>
    @endunless
</span>
