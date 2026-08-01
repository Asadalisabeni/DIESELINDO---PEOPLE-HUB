@extends('layouts.app')

@section('title', __('ui.nav.overview'))
@section('page-title', __('ui.nav.overview'))

@section('content')
    <section aria-labelledby="page-title" class="overflow-hidden rounded-3xl bg-navy-950 text-white shadow-panel">
        <div class="relative grid gap-10 px-6 py-9 sm:px-9 sm:py-12 lg:grid-cols-[1.4fr_.6fr] lg:items-end lg:px-12">
            <div class="pointer-events-none absolute -right-28 -top-32 size-80 rounded-full border-[64px] border-brand-500/10"></div>
            <div class="relative max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-400">{{ __('ui.home.eyebrow') }}</p>
                <h1 id="page-title" class="mt-4 text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">{{ __('ui.home.title') }}</h1>
                <p class="mt-5 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">{{ __('ui.home.description') }}</p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <x-button :href="route('design-system')">
                        {{ __('ui.actions.view_components') }}
                        <x-icon name="arrow-right" size="4" />
                    </x-button>
                    <x-badge variant="success" class="px-3 py-2">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        {{ __('ui.home.status_value') }}
                    </x-badge>
                </div>
            </div>
            <div class="relative hidden justify-self-end lg:block" aria-hidden="true">
                <div class="grid size-44 place-items-center rounded-[2rem] border border-white/10 bg-white/5 shadow-2xl backdrop-blur">
                    <span class="grid size-24 place-items-center rounded-3xl bg-brand-500 text-4xl font-black text-navy-950">PH</span>
                </div>
            </div>
        </div>
    </section>

    <section aria-label="{{ __('ui.home.metrics_label') }}" class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['status_label', 'status_value', 'lock', 'brand'],
            ['foundation_label', 'foundation_value', 'grid', 'neutral'],
            ['language_label', 'language_value', 'info', 'neutral'],
            ['accessibility_label', 'accessibility_value', 'check', 'success'],
        ] as [$label, $value, $icon, $badge])
            <article class="surface-panel rounded-2xl border p-5 shadow-panel">
                <div class="flex items-start justify-between gap-4">
                    <span class="grid size-10 place-items-center rounded-xl bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200">
                        <x-icon :name="$icon" />
                    </span>
                    <x-badge :variant="$badge">{{ __('ui.badge.active') }}</x-badge>
                </div>
                <p class="mt-5 text-sm font-medium text-secondary">{{ __('ui.home.'.$label) }}</p>
                <p class="mt-1 text-base font-bold text-primary">{{ __('ui.home.'.$value) }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[1.45fr_.55fr]" aria-labelledby="readiness-title">
        <div class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-7">
            <p class="section-kicker">{{ __('ui.app.foundation') }}</p>
            <h2 id="readiness-title" class="mt-2 text-2xl font-bold tracking-tight text-primary">{{ __('ui.home.readiness_title') }}</h2>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-secondary">{{ __('ui.home.readiness_description') }}</p>

            <div class="mt-7 grid gap-4 md:grid-cols-3">
                @foreach ([
                    ['tokens', 'tokens_detail', 'grid'],
                    ['shell', 'shell_detail', 'home'],
                    ['states', 'states_detail', 'info'],
                ] as [$title, $detail, $icon])
                    <div class="surface-muted rounded-xl p-5">
                        <x-icon :name="$icon" class="text-brand-600 dark:text-brand-400" />
                        <h3 class="mt-4 text-sm font-bold text-primary">{{ __('ui.home.'.$title) }}</h3>
                        <p class="mt-2 text-sm leading-6 text-secondary">{{ __('ui.home.'.$detail) }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <x-state-panel
            variant="empty"
            :title="__('ui.home.business_data_title')"
            :description="__('ui.home.business_data_description')"
            class="shadow-panel"
        />
    </section>
@endsection
