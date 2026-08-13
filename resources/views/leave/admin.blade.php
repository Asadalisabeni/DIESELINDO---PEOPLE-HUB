@extends('layouts.app')

@section('title', __('leave.admin_title'))
@section('page-title', __('leave.admin_title'))

@section('content')
    @if ($errors->any())<x-alert variant="danger" :title="__('ui.design.error_title')" class="mb-6"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
    <section class="rounded-3xl bg-navy-950 px-6 py-9 text-white shadow-panel sm:px-9"><p class="text-xs font-bold uppercase tracking-[.2em] text-brand-400">{{ __('leave.eyebrow') }}</p><h1 class="mt-3 text-3xl font-bold">{{ __('leave.admin_title') }}</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">{{ __('leave.admin_description') }}</p></section>
    @unless ($canManage)<x-alert variant="info" :title="__('leave.read_only')" class="mt-6">{{ __('leave.read_only_help') }}</x-alert>@endunless

    <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('leave.configured_types') }}</h2><div class="mt-5 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr><th class="py-2">{{ __('leave.legal_entity') }}</th><th>{{ __('leave.code') }}</th><th>{{ __('leave.name') }}</th><th>{{ __('leave.category') }}</th><th>{{ __('leave.paid') }}</th><th>{{ __('leave.requires_balance') }}</th><th>{{ __('leave.effective_from') }}</th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">@forelse($types as $type)<tr><td class="py-3">{{ $type->legalEntity?->code }}</td><td>{{ $type->code }}</td><td>{{ $type->name }}</td><td>{{ __('leave.categories.'.$type->category) }}</td><td>{{ $type->is_paid ? __('leave.yes') : __('leave.no') }}</td><td>{{ $type->requires_balance ? __('leave.yes') : __('leave.no') }}</td><td>{{ $type->policies->sortByDesc('version')->first()?->effective_from?->format('d M Y') }}</td></tr>@empty<tr><td colspan="7" class="py-5 text-secondary">{{ __('leave.no_records') }}</td></tr>@endforelse</tbody></table></div></section>

    @if ($canManage)
        <section class="mt-6 grid gap-6 xl:grid-cols-2">
            <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('leave.create_type') }}</h2><form method="POST" action="{{ route('leave.admin.types.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
                <x-form.select name="legal_entity_public_id" :label="__('leave.legal_entity')" required>@foreach($entities as $entity)<option value="{{ $entity->public_id }}">{{ $entity->display_name }}</option>@endforeach</x-form.select>
                <x-form.input name="code" :label="__('leave.code')" required /><x-form.input name="name" :label="__('leave.name')" required />
                <x-form.select name="category" :label="__('leave.category')">@foreach(__('leave.categories') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-form.select>
                @foreach(['is_paid' => 'paid', 'requires_balance' => 'requires_balance', 'requires_payroll_confirmation' => 'payroll_confirmation', 'carry_forward_enabled' => 'carry_forward'] as $name => $label)<x-form.select :name="$name" :label="__('leave.'.$label)"><option value="1">{{ __('leave.yes') }}</option><option value="0">{{ __('leave.no') }}</option></x-form.select>@endforeach
                <x-form.input name="evidence_required_from_days" type="number" min="1" :label="__('leave.evidence_from_days')" />
                <x-form.input name="eligibility_months" type="number" min="0" :label="__('leave.eligibility_months')" value="0" required />
                <x-form.input name="entitlement_quantity" type="number" min="0" step="0.01" :label="__('leave.entitlement_quantity')" value="0" required />
                <x-form.input name="validity_months" type="number" min="1" :label="__('leave.validity_months')" />
                <x-form.input name="carry_forward_limit" type="number" min="0" step="0.01" :label="__('leave.carry_forward_limit')" />
                <x-form.input name="minimum_notice_days" type="number" min="0" :label="__('leave.minimum_notice_days')" value="0" required />
                <x-form.input name="maximum_request_days" type="number" min="1" step="0.01" :label="__('leave.maximum_request_days')" />
                <x-form.input name="effective_from" type="date" :label="__('leave.effective_from')" required /><x-form.input name="effective_to" type="date" :label="__('leave.effective_to')" />
                <x-form.input name="approval_reminder_hours" type="number" min="1" :label="__('leave.approval_reminder_hours')" value="24" required />
                <x-form.input name="approval_escalation_hours" type="number" min="2" :label="__('leave.approval_escalation_hours')" value="72" required />
                <div class="sm:col-span-2"><x-button type="submit">{{ __('leave.create_type') }}</x-button></div>
            </form></article>

            <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('leave.create_policy') }}</h2><form method="POST" action="{{ route('leave.admin.policies.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
                <x-form.select name="leave_type_public_id" :label="__('leave.leave_type')" required>@foreach($types as $type)<option value="{{ $type->public_id }}">{{ $type->legalEntity?->code }} · {{ $type->name }}</option>@endforeach</x-form.select>
                <x-form.input name="eligibility_months" type="number" min="0" :label="__('leave.eligibility_months')" required />
                <x-form.input name="entitlement_quantity" type="number" min="0" step="0.01" :label="__('leave.entitlement_quantity')" required />
                <x-form.input name="validity_months" type="number" min="1" :label="__('leave.validity_months')" />
                <x-form.select name="carry_forward_enabled" :label="__('leave.carry_forward')"><option value="0">{{ __('leave.no') }}</option><option value="1">{{ __('leave.yes') }}</option></x-form.select>
                <x-form.input name="carry_forward_limit" type="number" min="0" step="0.01" :label="__('leave.carry_forward_limit')" />
                <x-form.input name="minimum_notice_days" type="number" min="0" :label="__('leave.minimum_notice_days')" required />
                <x-form.input name="maximum_request_days" type="number" min="1" step="0.01" :label="__('leave.maximum_request_days')" />
                <x-form.input name="effective_from" type="date" :label="__('leave.effective_from')" required /><x-form.input name="effective_to" type="date" :label="__('leave.effective_to')" />
                <div class="sm:col-span-2"><x-button type="submit">{{ __('leave.create_policy') }}</x-button></div>
            </form></article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-2">
            <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('leave.grant_entitlement') }}</h2><form method="POST" action="{{ route('leave.admin.entitlements.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
                <x-form.select name="employee_public_id" :label="__('leave.employee')" required>@foreach($employees as $employee)<option value="{{ $employee->public_id }}">{{ $employee->employee_number }} · {{ $employee->full_name }}</option>@endforeach</x-form.select>
                <x-form.select name="leave_type_public_id" :label="__('leave.leave_type')" required>@foreach($types as $type)<option value="{{ $type->public_id }}">{{ $type->legalEntity?->code }} · {{ $type->name }}</option>@endforeach</x-form.select>
                <x-form.input name="grant_reference" :label="__('leave.grant_reference')" required /><x-form.input name="quantity" type="number" min="0.01" step="0.01" :label="__('leave.quantity')" required />
                <x-form.input name="valid_from" type="date" :label="__('leave.valid_from')" required /><x-form.input name="valid_to" type="date" :label="__('leave.valid_to')" />
                <x-form.select name="source" :label="__('leave.source')">@foreach(__('leave.sources') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-form.select>
                <x-form.input name="reason" :label="__('leave.reason')" required />
                <div class="sm:col-span-2"><x-button type="submit">{{ __('leave.grant_entitlement') }}</x-button></div>
            </form></article>

            <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('leave.delegation') }}</h2><form method="POST" action="{{ route('leave.admin.delegations.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
                <x-form.select name="legal_entity_public_id" :label="__('leave.legal_entity')">@foreach($entities as $entity)<option value="{{ $entity->public_id }}">{{ $entity->display_name }}</option>@endforeach</x-form.select>
                <x-form.select name="delegator_user_id" :label="__('leave.delegator')">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</x-form.select>
                <x-form.select name="delegate_user_id" :label="__('leave.delegate')">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</x-form.select>
                <x-form.input name="reason" :label="__('leave.reason')" required /><x-form.input name="effective_from" type="date" :label="__('leave.effective_from')" required /><x-form.input name="effective_to" type="date" :label="__('leave.effective_to')" required />
                <div class="sm:col-span-2"><x-button type="submit">{{ __('leave.delegation') }}</x-button></div>
            </form><div class="mt-5 space-y-2">@foreach($delegations as $delegation)<div class="flex items-center justify-between gap-3 rounded-xl border p-3 text-sm dark:border-slate-800"><span>{{ $delegation->delegate?->name }} · {{ $delegation->effective_from?->format('d M') }}—{{ $delegation->effective_to?->format('d M Y') }} · {{ $delegation->status }}</span>@if($delegation->status === 'active')<form method="POST" action="{{ route('leave.admin.delegations.revoke', $delegation) }}">@csrf @method('PUT')<x-button type="submit" variant="danger">{{ __('leave.revoke') }}</x-button></form>@endif</div>@endforeach</div></article>
        </section>
    @endif

    <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel"><div class="flex flex-wrap items-end justify-between gap-4"><div><h2 class="text-xl font-bold text-primary">{{ __('leave.report') }}</h2><p class="mt-2 text-sm text-secondary">{{ __('leave.report_help') }}</p></div>@if($canExport)<form method="GET" action="{{ route('leave.admin.report.export') }}" class="flex flex-wrap items-end gap-3"><x-form.input name="from" type="date" :label="__('leave.start_date')" /><x-form.input name="to" type="date" :label="__('leave.end_date')" /><x-button type="submit">{{ __('leave.export_csv') }}</x-button></form>@endif</div><div class="mt-5 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr><th class="py-2">{{ __('leave.employee') }}</th><th>{{ __('leave.leave_type') }}</th><th>{{ __('leave.start_date') }}</th><th>{{ __('leave.end_date') }}</th><th>{{ __('leave.quantity') }}</th><th>Status</th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">@forelse($requests as $request)<tr><td class="py-3">{{ $request->employee?->full_name }}</td><td>{{ $request->type?->name }}</td><td>{{ $request->start_date?->format('d M Y') }}</td><td>{{ $request->end_date?->format('d M Y') }}</td><td>{{ $request->total_days }}</td><td>{{ __('leave.statuses.'.$request->requestStatus()->value) }}</td></tr>@empty<tr><td colspan="6" class="py-5 text-secondary">{{ __('leave.no_records') }}</td></tr>@endforelse</tbody></table></div></section>

    <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('leave.balance_ledger') }}</h2><div class="mt-5 space-y-4">@forelse($entitlements as $entitlement)<article class="rounded-xl border p-4 dark:border-slate-800"><div class="flex flex-wrap items-center justify-between gap-3"><span><strong class="text-primary">{{ $entitlement->employee?->full_name }}</strong><span class="ml-2 text-secondary">{{ $entitlement->type?->name }} · {{ $entitlement->grant_reference }}</span></span><x-badge variant="success">{{ $entitlement->balance }}</x-badge></div>@if($canAdjust)<form method="POST" action="{{ route('leave.admin.adjustments.store', $entitlement) }}" class="mt-4 grid gap-3 md:grid-cols-[9rem_11rem_1fr_auto]">@csrf<x-form.input name="quantity" type="number" step="0.01" :label="__('leave.quantity')" required /><x-form.input name="effective_date" type="date" :label="__('leave.effective_date')" required /><x-form.input name="reason" :label="__('leave.reason')" required /><div class="flex items-end"><x-button type="submit">{{ __('leave.adjust_balance') }}</x-button></div></form>@endif</article>@empty<p class="text-sm text-secondary">{{ __('leave.no_records') }}</p>@endforelse</div><div class="mt-6 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr><th class="py-2">{{ __('leave.employee') }}</th><th>{{ __('leave.leave_type') }}</th><th>{{ __('leave.source') }}</th><th>{{ __('leave.quantity') }}</th><th>{{ __('leave.effective_date') }}</th><th>{{ __('leave.reference_key') }}</th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">@foreach($ledger as $entry)<tr><td class="py-3">{{ $entry->entitlement?->employee?->full_name }}</td><td>{{ $entry->entitlement?->type?->name }}</td><td>{{ $entry->entry_type->value }}</td><td>{{ $entry->quantity }}</td><td>{{ $entry->effective_date?->format('d M Y') }}</td><td class="font-mono text-xs">{{ $entry->reference_key }}</td></tr>@endforeach</tbody></table></div></section>
@endsection
