@extends('layouts.app')

@section('title', __('employee.title'))
@section('page-title', __('employee.title'))

@section('content')
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="section-kicker">{{ __('employee.eyebrow') }}</p>
            <h1 class="mt-2 text-3xl font-bold text-primary">{{ __('employee.title') }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">{{ __('employee.description') }}</p>
        </div>
        @can('employees.create')<x-button :href="route('employees.create')">{{ __('employee.create') }}</x-button>@endcan
    </header>

    <form method="GET" action="{{ route('employees.index') }}" class="surface-panel mt-6 grid gap-4 rounded-2xl border p-5 shadow-panel md:grid-cols-[1fr_280px_auto] md:items-end">
        <x-form.input name="search" type="search" :label="__('employee.search')" :value="request('search')" />
        <x-form.select name="legal_entity" :label="__('employee.legal_entity')">
            <option value="">{{ __('employee.all_entities') }}</option>
            @foreach ($legalEntities as $entity)
                <option value="{{ $entity->public_id }}" @selected(request('legal_entity') === $entity->public_id)>{{ $entity->display_name }}</option>
            @endforeach
        </x-form.select>
        <x-button type="submit">{{ __('ui.actions.confirm') }}</x-button>
    </form>

    <section class="surface-panel mt-6 overflow-hidden rounded-2xl border shadow-panel">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-700">
                <thead class="surface-muted text-xs uppercase tracking-wide text-secondary">
                    <tr>
                        <th class="px-5 py-3">{{ __('employee.full_name') }}</th>
                        <th class="px-5 py-3">{{ __('employee.legal_entity') }}</th>
                        <th class="px-5 py-3">{{ __('employee.department') }}</th>
                        <th class="px-5 py-3">{{ __('employee.position') }}</th>
                        <th class="px-5 py-3">{{ __('organization.status_label') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($employees as $employee)
                        <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                            <td class="px-5 py-4">
                                <a href="{{ route('employees.show', $employee) }}" class="font-bold text-primary hover:text-brand-600">{{ $employee->full_name }}</a>
                                <p class="mt-1 text-xs text-secondary">{{ $employee->employee_number }}</p>
                            </td>
                            <td class="px-5 py-4 text-secondary">{{ $employee->legalEntity->display_name }}</td>
                            <td class="px-5 py-4 text-secondary">{{ $employee->currentEmployment?->department?->name ?? '—' }}</td>
                            <td class="px-5 py-4 text-secondary">{{ $employee->currentEmployment?->position?->name ?? '—' }}</td>
                            <td class="px-5 py-4"><x-badge :variant="$employee->status->value === 'active' ? 'success' : 'neutral'">{{ __('employee.'.$employee->status->value) }}</x-badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-secondary">{{ __('employee.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($employees->hasPages())<div class="border-t border-slate-200 p-5 dark:border-slate-700">{{ $employees->links() }}</div>@endif
    </section>
@endsection
