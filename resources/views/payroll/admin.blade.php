@extends('layouts.app')

@section('title', __('payroll.title'))
@section('page-title', __('payroll.title'))

@section('content')
    @if ($errors->any())
        <x-alert variant="danger" :title="__('ui.design.error_title')" class="mb-6"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>
    @endif
    <section class="rounded-3xl bg-navy-950 px-6 py-9 text-white shadow-panel sm:px-9">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-brand-400">{{ __('payroll.eyebrow') }}</p>
        <h1 class="mt-3 text-3xl font-bold">{{ __('payroll.title') }}</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">{{ __('payroll.description') }}</p>
    </section>
    @unless($canConfigureSalary || $canApproveSalary || $canPrepare || $canValidate)
        <x-alert variant="info" :title="__('payroll.read_only')" class="mt-6">{{ __('payroll.read_only_help') }}</x-alert>
    @endunless
    <x-alert variant="warning" :title="__('payroll.phase11_boundary')" class="mt-6">{{ __('payroll.phase11_boundary') }}</x-alert>

    <section class="mt-6 grid gap-6 xl:grid-cols-2">
        <article class="surface-panel rounded-2xl border p-6 shadow-panel">
            <h2 class="text-xl font-bold text-primary">{{ __('payroll.components') }}</h2>
            <div class="mt-5 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr><th class="py-2">{{ __('payroll.code') }}</th><th>{{ __('payroll.type') }}</th><th>{{ __('payroll.calculation_type') }}</th><th>{{ __('payroll.effective_from') }}</th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">@forelse($components as $component)<tr><td class="py-3"><strong>{{ $component->code }}</strong><span class="block text-xs text-secondary">{{ $component->legalEntity?->code }}</span></td><td>{{ __('payroll.types.'.$component->componentType()->value) }}</td><td>{{ __('payroll.calculation_types.'.$component->calculation_type) }}</td><td>{{ $component->effective_from?->format('d M Y') }}</td></tr>@empty<tr><td colspan="4" class="py-4 text-secondary">{{ __('payroll.no_records') }}</td></tr>@endforelse</tbody></table></div>
            @if($canConfigureSalary)
                <form method="POST" action="{{ route('payroll.admin.components.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2">@csrf
                    <x-form.select name="legal_entity_public_id" :label="__('payroll.legal_entity')" required>@foreach($entities as $entity)<option value="{{ $entity->public_id }}">{{ $entity->display_name }}</option>@endforeach</x-form.select>
                    <x-form.input name="code" :label="__('payroll.code')" required />
                    <x-form.input name="name" :label="__('payroll.name')" required />
                    <x-form.select name="type" :label="__('payroll.type')" required>@foreach(__('payroll.types') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-form.select>
                    <x-form.select name="calculation_type" :label="__('payroll.calculation_type')" required>@foreach(__('payroll.calculation_types') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-form.select>
                    <x-form.select name="rounding_scale" :label="__('payroll.rounding_scale')"><option value="0">0</option><option value="2">2</option><option value="4">4</option></x-form.select>
                    <x-form.select name="rounding_mode" :label="__('payroll.rounding_mode')">@foreach(__('payroll.rounding_modes') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-form.select>
                    <label class="flex items-center gap-2 text-sm font-bold text-primary"><input type="checkbox" name="taxable" value="1">{{ __('payroll.taxable') }}</label>
                    <label class="flex items-center gap-2 text-sm font-bold text-primary"><input type="checkbox" name="bpjs_eligible" value="1">{{ __('payroll.bpjs_eligible') }}</label>
                    <x-form.input name="effective_from" type="date" :label="__('payroll.effective_from')" required />
                    <x-form.input name="effective_to" type="date" :label="__('payroll.effective_to')" />
                    <div class="sm:col-span-2"><x-button type="submit">{{ __('payroll.create_component') }}</x-button></div>
                </form>
            @endif
        </article>

        <article class="surface-panel rounded-2xl border p-6 shadow-panel">
            <h2 class="text-xl font-bold text-primary">{{ __('payroll.salaries') }}</h2>
            <div class="mt-5 space-y-3">@forelse($salaries as $salary)<div class="rounded-xl border p-4 dark:border-slate-800"><div class="flex flex-wrap items-center justify-between gap-3"><div><strong class="text-primary">{{ $salary->employee?->full_name }}</strong><p class="text-xs text-secondary">{{ $salary->effective_from?->format('d M Y') }} · {{ __('payroll.statuses.'.$salary->historyStatus()->value) }} · {{ $salary->components->count() }} {{ __('payroll.components') }}</p></div>@if($canApproveSalary && $salary->historyStatus()->value === 'draft')<form method="POST" action="{{ route('payroll.admin.salaries.approve', $salary->public_id) }}">@csrf @method('PUT')<x-button type="submit">{{ __('payroll.approve_salary') }}</x-button></form>@endif</div><p class="mt-2 break-all font-mono text-[10px] text-secondary">{{ $salary->version_checksum }}</p></div>@empty<p class="text-sm text-secondary">{{ __('payroll.no_records') }}</p>@endforelse</div>
            @if($canConfigureSalary)
                <form method="POST" action="{{ route('payroll.admin.salaries.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2">@csrf
                    <x-form.select name="employee_public_id" :label="__('payroll.employee')">@foreach($employees as $employee)<option value="{{ $employee->public_id }}">{{ $employee->employee_number }} · {{ $employee->full_name }}</option>@endforeach</x-form.select>
                    <x-form.input name="effective_from" type="date" :label="__('payroll.effective_from')" required />
                    <x-form.input name="effective_to" type="date" :label="__('payroll.effective_to')" />
                    <x-form.input name="reason" :label="__('payroll.reason')" required />
                    <div class="sm:col-span-2"><label for="components_json" class="block text-sm font-bold text-primary">{{ __('payroll.components_json') }}</label><textarea id="components_json" name="components_json" rows="5" class="control mt-2" required>{{ old('components_json') }}</textarea><p class="mt-2 text-xs text-secondary">{{ __('payroll.components_json_help') }}</p></div>
                    <div class="sm:col-span-2"><x-button type="submit">{{ __('payroll.draft_salary') }}</x-button></div>
                </form>
            @endif
        </article>
    </section>

    @if($canViewPayroll)
        <section class="mt-6 grid gap-6 xl:grid-cols-2">
            <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('payroll.groups') }}</h2>
                <div class="mt-4 space-y-3">@forelse($groups as $group)<div class="rounded-xl border p-4 dark:border-slate-800"><strong>{{ $group->code }} · {{ $group->name }}</strong><p class="text-xs text-secondary">{{ __('payroll.proration_bases.'.$group->proration_basis) }} · {{ $group->memberships->count() }} {{ __('payroll.employees') }}</p></div>@empty<p class="text-sm text-secondary">{{ __('payroll.no_records') }}</p>@endforelse</div>
                @if($canPrepare)<form method="POST" action="{{ route('payroll.admin.groups.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2">@csrf
                    <x-form.select name="legal_entity_public_id" :label="__('payroll.legal_entity')">@foreach($entities as $entity)<option value="{{ $entity->public_id }}">{{ $entity->display_name }}</option>@endforeach</x-form.select><x-form.input name="code" :label="__('payroll.code')" required /><x-form.input name="name" :label="__('payroll.name')" required />
                    <x-form.select name="proration_basis" :label="__('payroll.proration_basis')">@foreach(__('payroll.proration_bases') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-form.select>
                    <x-form.input name="cutoff_start_day" type="number" min="1" max="31" :label="__('payroll.cutoff_start_day')" required /><x-form.input name="cutoff_end_day" type="number" min="1" max="31" :label="__('payroll.cutoff_end_day')" required /><x-form.input name="payment_day" type="number" min="1" max="31" :label="__('payroll.payment_day')" required />
                    <x-form.select name="payment_date_adjustment" :label="__('payroll.payment_adjustment')">@foreach(__('payroll.payment_adjustments') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-form.select><div class="sm:col-span-2"><x-button type="submit">{{ __('payroll.create_group') }}</x-button></div></form>
                    <form method="POST" action="{{ route('payroll.admin.memberships.store') }}" class="mt-7 grid gap-4 sm:grid-cols-2">@csrf
                        <x-form.select name="payroll_group_public_id" :label="__('payroll.group')">@foreach($groups as $group)<option value="{{ $group->public_id }}">{{ $group->code }} · {{ $group->name }}</option>@endforeach</x-form.select><x-form.select name="employee_public_id" :label="__('payroll.employee')">@foreach($employees as $employee)<option value="{{ $employee->public_id }}">{{ $employee->employee_number }} · {{ $employee->full_name }}</option>@endforeach</x-form.select><x-form.input name="effective_from" type="date" :label="__('payroll.effective_from')" required /><x-form.input name="effective_to" type="date" :label="__('payroll.effective_to')" /><div class="sm:col-span-2"><x-form.input name="reason" :label="__('payroll.reason')" required /></div><div class="sm:col-span-2"><x-button type="submit">{{ __('payroll.create_membership') }}</x-button></div>
                    </form>@endif
            </article>

            <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('payroll.periods') }}</h2>
                <div class="mt-4 space-y-3">@forelse($periods as $period)<div class="rounded-xl border p-4 dark:border-slate-800"><div class="flex flex-wrap items-center justify-between gap-3"><div><strong>{{ $period->period_key }} · {{ __('payroll.period_types.'.$period->period_type) }}</strong><p class="text-xs text-secondary">{{ $period->payroll_start?->format('d M') }}—{{ $period->payroll_end?->format('d M Y') }} · {{ __('payroll.statuses.'.$period->periodStatus()->value) }}</p></div>@if($canPrepare && $period->periodStatus()->value === 'open')<form method="POST" action="{{ route('payroll.admin.runs.store', $period->public_id) }}">@csrf<input type="hidden" name="run_type" value="regular"><x-button type="submit">{{ __('payroll.create_run') }}</x-button></form>@endif</div></div>@empty<p class="text-sm text-secondary">{{ __('payroll.no_records') }}</p>@endforelse</div>
                @if($canPrepare)<form method="POST" action="{{ route('payroll.admin.periods.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2">@csrf
                    <x-form.select name="payroll_group_public_id" :label="__('payroll.group')">@foreach($groups as $group)<option value="{{ $group->public_id }}">{{ $group->code }} · {{ $group->name }}</option>@endforeach</x-form.select><x-form.input name="period_key" type="month" :label="__('payroll.period_key')" required /><x-form.select name="period_type" :label="__('payroll.period_type')">@foreach(__('payroll.period_types') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-form.select><x-form.input name="payroll_start" type="date" :label="__('payroll.payroll_start')" required /><x-form.input name="payroll_end" type="date" :label="__('payroll.payroll_end')" required /><x-form.input name="attendance_cutoff_start" type="date" :label="__('payroll.cutoff_start')" required /><x-form.input name="attendance_cutoff_end" type="date" :label="__('payroll.cutoff_end')" required /><x-form.input name="payment_date" type="date" :label="__('payroll.payment_date')" required /><div class="sm:col-span-2"><x-button type="submit">{{ __('payroll.create_period') }}</x-button></div>
                </form>@endif
            </article>
        </section>

        <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('payroll.runs') }}</h2><div class="mt-5 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr><th class="py-2">{{ __('payroll.period_key') }}</th><th>{{ __('payroll.version') }}</th><th>{{ __('payroll.status_label') }}</th><th>{{ __('payroll.employees') }}</th><th>{{ __('payroll.gross') }}</th><th>{{ __('payroll.net') }}</th><th></th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">@forelse($runs as $run)<tr><td class="py-3">{{ $run->period?->period_key }}</td><td>v{{ $run->version }}</td><td>{{ __('payroll.statuses.'.$run->runStatus()->value) }}</td><td>{{ $run->employees_count }}</td><td>{{ $run->gross_total }} {{ $run->currency }}</td><td>{{ $run->net_total }} {{ $run->currency }}</td><td><div class="flex flex-wrap justify-end gap-2"><x-button variant="ghost" :href="route('payroll.admin.index', ['run' => $run->public_id])">{{ __('payroll.view_evidence') }}</x-button>@if($canPrepare && $run->runStatus()->value === 'draft')<form method="POST" action="{{ route('payroll.admin.runs.calculate', $run->public_id) }}">@csrf<x-button type="submit">{{ __('payroll.calculate') }}</x-button></form>@endif @if($canValidate && $run->runStatus()->value === 'calculated')<form method="POST" action="{{ route('payroll.admin.runs.validate', $run->public_id) }}">@csrf<x-button type="submit">{{ __('payroll.validate') }}</x-button></form>@endif @if($canExport && $run->runStatus()->value !== 'draft')<x-button variant="secondary" :href="route('payroll.admin.runs.export', $run->public_id)">{{ __('payroll.export_register') }}</x-button>@endif</div></td></tr>@empty<tr><td colspan="7" class="py-4 text-secondary">{{ __('payroll.no_records') }}</td></tr>@endforelse</tbody></table></div></section>

        @if($selectedRun)
            <section class="mt-6 grid gap-6 xl:grid-cols-2">
                <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('payroll.validation_findings') }}</h2><div class="mt-4 space-y-3">@forelse($selectedRun->findings as $finding)<div class="rounded-xl border p-4 dark:border-slate-800"><div class="flex items-center justify-between gap-3"><strong>{{ $finding->code }}</strong><x-badge :variant="$finding->severity === 'error' ? 'danger' : 'warning'">{{ strtoupper($finding->severity) }}</x-badge></div><p class="mt-2 text-sm text-secondary">{{ __($finding->message_key) }}</p></div>@empty<p class="text-sm text-secondary">{{ __('payroll.no_records') }}</p>@endforelse</div></article>
                <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('payroll.items') }}</h2><div class="mt-4 max-h-[36rem] space-y-3 overflow-y-auto">@foreach($selectedRun->employees as $snapshot)<div class="rounded-xl border p-4 dark:border-slate-800"><div class="flex items-start justify-between gap-3"><div><strong>{{ $snapshot->employee_snapshot['employee_number'] ?? '' }} · {{ $snapshot->employee_snapshot['full_name'] ?? '' }}</strong><p class="text-xs text-secondary">{{ $snapshot->payable_days }}/{{ $snapshot->period_days }} · {{ $snapshot->validation_status }}</p></div><strong>{{ $snapshot->net_total }} {{ $selectedRun->currency }}</strong></div><ul class="mt-3 space-y-1 text-xs text-secondary">@foreach($snapshot->items as $item)<li class="flex justify-between gap-3"><span>{{ $item->component_code }} · {{ $item->component_name }}</span><span>{{ $item->amount }}</span></li>@endforeach</ul><p class="mt-3 break-all font-mono text-[9px] text-secondary">{{ $snapshot->snapshot_checksum }}</p></div>@endforeach</div></article>
            </section>
        @endif
    @endif
@endsection
