@extends('layouts.app')

@section('title', __('ui.design.title'))
@section('page-title', __('ui.design.title'))

@section('content')
    <header class="flex flex-col gap-5 border-b border-slate-200 pb-7 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
        <div class="max-w-3xl">
            <p class="section-kicker">{{ __('ui.design.eyebrow') }}</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-primary sm:text-4xl">{{ __('ui.design.title') }}</h1>
            <p class="mt-3 text-base leading-7 text-secondary">{{ __('ui.design.description') }}</p>
        </div>
        <x-badge variant="brand">v1.0 · Phase 3</x-badge>
    </header>

    <div class="mt-8 space-y-8">
        <section class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-7" aria-labelledby="colors-title">
            <div class="max-w-3xl">
                <h2 id="colors-title" class="text-xl font-bold text-primary">{{ __('ui.design.colors') }}</h2>
                <p class="mt-2 text-sm leading-6 text-secondary">{{ __('ui.design.colors_description') }}</p>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    ['brand', 'bg-brand-600', '#EA580C'],
                    ['canvas', 'bg-navy-950', '#101B2D'],
                    ['success', 'bg-emerald-600', '#059669'],
                    ['warning', 'bg-amber-500', '#F59E0B'],
                    ['danger', 'bg-red-600', '#DC2626'],
                ] as [$name, $color, $hex])
                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <div class="h-20 {{ $color }}"></div>
                        <div class="surface-panel p-3">
                            <p class="text-sm font-bold text-primary">{{ __('ui.design.'.$name) }}</p>
                            <p class="mt-1 font-mono text-xs text-secondary">{{ $hex }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-8 xl:grid-cols-2">
            <div class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-7" aria-labelledby="type-title">
                <h2 id="type-title" class="text-xl font-bold text-primary">{{ __('ui.design.typography') }}</h2>
                <p class="mt-2 text-sm leading-6 text-secondary">{{ __('ui.design.typography_description') }}</p>
                <p class="mt-8 text-3xl font-bold tracking-tight text-primary sm:text-4xl">{{ __('ui.design.display_sample') }}</p>
                <p class="mt-5 text-base leading-7 text-secondary">{{ __('ui.design.body_sample') }}</p>
                <p class="mt-5 font-mono text-xs text-secondary">12 / 14 / 16 / 20 / 24 / 36</p>
            </div>

            <div class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-7" aria-labelledby="actions-title">
                <h2 id="actions-title" class="text-xl font-bold text-primary">{{ __('ui.design.actions') }}</h2>
                <div class="mt-6 flex flex-wrap gap-3">
                    <x-button>{{ __('ui.actions.save') }}</x-button>
                    <x-button variant="secondary">{{ __('ui.actions.cancel') }}</x-button>
                    <x-button variant="ghost">{{ __('ui.actions.learn_more') }}</x-button>
                    <x-button variant="danger">{{ __('ui.actions.confirm') }}</x-button>
                    <x-button disabled>{{ __('ui.actions.save') }}</x-button>
                </div>
                <div class="mt-7 flex flex-wrap gap-2">
                    <x-badge variant="success">{{ __('ui.badge.active') }}</x-badge>
                    <x-badge>{{ __('ui.badge.draft') }}</x-badge>
                    <x-badge variant="warning">{{ __('ui.badge.review') }}</x-badge>
                    <x-badge variant="danger">{{ __('ui.badge.locked') }}</x-badge>
                </div>
            </div>
        </section>

        <section class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-7" aria-labelledby="forms-title">
            <div class="max-w-3xl">
                <h2 id="forms-title" class="text-xl font-bold text-primary">{{ __('ui.design.forms') }}</h2>
                <p class="mt-2 text-sm leading-6 text-secondary">{{ __('ui.design.forms_description') }}</p>
            </div>
            <form class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3" onsubmit="return false">
                <x-form.input name="employee_name" :label="__('ui.design.employee_name')" :hint="__('ui.design.employee_hint')" :placeholder="__('ui.design.employee_placeholder')" />
                <x-form.input name="employee_email" type="email" :label="__('ui.design.email')" value="invalid@" :error="__('ui.design.email_error')" />
                <x-form.select name="legal_entity" :label="__('ui.design.entity')">
                    <option value="">{{ __('ui.design.select_entity') }}</option>
                    <option>PT Dieselindo Utama Nusa</option>
                </x-form.select>
            </form>
        </section>

        <section class="surface-panel overflow-hidden rounded-2xl border shadow-panel" aria-labelledby="table-title">
            <div class="p-6 sm:p-7">
                <h2 id="table-title" class="text-xl font-bold text-primary">{{ __('ui.design.table') }}</h2>
                <p class="mt-2 text-sm leading-6 text-secondary">{{ __('ui.design.table_description') }}</p>
            </div>
            <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-700">
                    <caption class="sr-only">{{ __('ui.design.table_description') }}</caption>
                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500 dark:bg-navy-950 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-6 py-3.5">{{ __('ui.design.employee') }}</th>
                            <th scope="col" class="px-6 py-3.5">{{ __('ui.design.department') }}</th>
                            <th scope="col" class="px-6 py-3.5">{{ __('ui.design.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-secondary">{{ __('ui.design.no_records') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="feedback-title">
            <h2 id="feedback-title" class="text-xl font-bold text-primary">{{ __('ui.design.feedback') }}</h2>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @foreach (['info', 'success', 'warning', 'danger'] as $variant)
                    <x-alert :variant="$variant" :title="__('ui.design.'.$variant.'_title')">
                        {{ __('ui.design.'.$variant.'_body') }}
                    </x-alert>
                @endforeach
            </div>
        </section>

        <section class="grid gap-8 xl:grid-cols-2">
            <div x-data="{ tab: 'overview' }" class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-7" aria-labelledby="tabs-title">
                <h2 id="tabs-title" class="text-xl font-bold text-primary">{{ __('ui.design.tabs') }}</h2>
                <div class="mt-5 flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-700" role="tablist">
                    @foreach (['overview', 'activity', 'documents'] as $tab)
                        <button
                            type="button"
                            id="{{ $tab }}-tab"
                            role="tab"
                            :aria-selected="tab === '{{ $tab }}'"
                            aria-controls="{{ $tab }}-panel"
                            x-on:click="tab = '{{ $tab }}'"
                            x-on:keydown.right.prevent="tab = '{{ $tab === 'overview' ? 'activity' : ($tab === 'activity' ? 'documents' : 'overview') }}'"
                            x-on:keydown.left.prevent="tab = '{{ $tab === 'overview' ? 'documents' : ($tab === 'activity' ? 'overview' : 'activity') }}'"
                            class="-mb-px whitespace-nowrap border-b-2 px-4 py-3 text-sm font-bold transition"
                            :class="tab === '{{ $tab }}' ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-300' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100'"
                        >{{ __('ui.design.'.$tab.'_tab') }}</button>
                    @endforeach
                </div>
                @foreach (['overview', 'activity', 'documents'] as $tab)
                    <div x-show="tab === '{{ $tab }}'" id="{{ $tab }}-panel" role="tabpanel" aria-labelledby="{{ $tab }}-tab" class="py-6 text-sm leading-6 text-secondary">
                        {{ __('ui.design.'.$tab.'_panel') }}
                    </div>
                @endforeach
            </div>

            <div class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-7" aria-labelledby="overlays-title">
                <h2 id="overlays-title" class="text-xl font-bold text-primary">{{ __('ui.design.overlays') }}</h2>
                <div class="mt-6 flex flex-wrap gap-3">
                    <x-button x-on:click="$dispatch('open-modal', 'confirmation-demo')">{{ __('ui.actions.open_modal') }}</x-button>
                    <x-button variant="secondary" x-on:click="$dispatch('open-drawer', 'details-demo')">{{ __('ui.actions.open_drawer') }}</x-button>
                    <x-button variant="ghost" x-on:click="$dispatch('peoplehub-toast')">{{ __('ui.actions.show_toast') }}</x-button>
                </div>
                <div class="surface-muted mt-7 rounded-xl p-5">
                    <x-skeleton :lines="4" />
                </div>
            </div>
        </section>

        <section aria-labelledby="states-title">
            <h2 id="states-title" class="text-xl font-bold text-primary">{{ __('ui.design.states') }}</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <x-state-panel variant="loading" :title="__('ui.design.loading_title')" :description="__('ui.design.loading_body')" />
                <x-state-panel variant="empty" :title="__('ui.design.empty_title')" :description="__('ui.design.empty_body')" />
                <x-state-panel variant="error" :title="__('ui.design.error_title')" :description="__('ui.design.error_body')">
                    <x-button variant="secondary">{{ __('ui.actions.retry') }}</x-button>
                </x-state-panel>
                <x-state-panel variant="denied" :title="__('ui.design.denied_title')" :description="__('ui.design.denied_body')">
                    <x-button variant="secondary">{{ __('ui.actions.go_back') }}</x-button>
                </x-state-panel>
            </div>
        </section>
    </div>

    <x-modal name="confirmation-demo" :title="__('ui.design.modal_title')">
        <p>{{ __('ui.design.modal_body') }}</p>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">{{ __('ui.actions.cancel') }}</x-button>
            <x-button x-on:click="open = false; $dispatch('peoplehub-toast')">{{ __('ui.actions.confirm') }}</x-button>
        </x-slot:footer>
    </x-modal>

    <x-drawer name="details-demo" :title="__('ui.design.drawer_title')">
        <p>{{ __('ui.design.drawer_body') }}</p>
        <div class="surface-muted mt-6 rounded-xl p-5">
            <x-skeleton :lines="5" />
        </div>
    </x-drawer>
@endsection
