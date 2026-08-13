@extends('layouts.app')

@section('title', __('leave.title'))
@section('page-title', __('leave.title'))

@section('content')
    @if ($errors->any())
        <x-alert variant="danger" :title="__('ui.design.error_title')" class="mb-6"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>
    @endif

    <section class="overflow-hidden rounded-3xl bg-navy-950 px-6 py-9 text-white shadow-panel sm:px-9" aria-labelledby="leave-title">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-brand-400">{{ __('leave.eyebrow') }}</p>
        <h1 id="leave-title" class="mt-3 text-3xl font-bold">{{ $employee->full_name }}</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">{{ __('leave.description') }}</p>
        <div class="mt-6 flex flex-wrap gap-2"><x-badge variant="brand">{{ $employee->employee_number }}</x-badge><x-badge variant="success">{{ $employee->legalEntity?->display_name }}</x-badge></div>
    </section>

    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="{{ __('leave.balance') }}">
        @forelse ($entitlements as $entitlement)
            <article class="surface-panel rounded-2xl border p-5 shadow-panel"><div class="flex items-start justify-between gap-3"><div><p class="font-bold text-primary">{{ $entitlement->type?->name }}</p><p class="mt-1 text-xs text-secondary">{{ $entitlement->grant_reference }}</p></div><x-badge :variant="((float) $entitlement->balance) > 0 ? 'success' : 'neutral'">{{ $entitlement->status }}</x-badge></div><p class="mt-5 text-3xl font-black text-primary">{{ number_format((float) $entitlement->balance, 2) }}</p><p class="mt-1 text-sm text-secondary">{{ __('leave.balance') }} · {{ $entitlement->valid_to ? __('leave.valid_until', ['date' => $entitlement->valid_to->format('d M Y')]) : __('leave.no_expiry') }}</p></article>
        @empty
            <x-state-panel icon="calendar" :title="__('leave.no_records')" />
        @endforelse
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
        <article class="surface-panel rounded-2xl border p-6 shadow-panel">
            <h2 class="text-xl font-bold text-primary">{{ __('leave.request_leave') }}</h2>
            @if ($types->isEmpty())
                <x-alert variant="warning" :title="__('leave.leave_type')" class="mt-5">{{ __('leave.no_records') }}</x-alert>
            @else
                <form method="POST" action="{{ route('leave.requests.store') }}" enctype="multipart/form-data" class="mt-5 space-y-5">@csrf
                    <x-form.select name="leave_type_public_id" :label="__('leave.leave_type')" required>@foreach ($types as $type)<option value="{{ $type->public_id }}" @selected(old('leave_type_public_id') === $type->public_id)>{{ $type->name }} · {{ $type->is_paid ? __('leave.paid') : __('leave.categories.unpaid') }}</option>@endforeach</x-form.select>
                    <div class="grid gap-5 sm:grid-cols-2"><x-form.input name="start_date" type="date" :label="__('leave.start_date')" :value="old('start_date')" required /><x-form.input name="end_date" type="date" :label="__('leave.end_date')" :value="old('end_date')" required /></div>
                    <div><label for="leave_reason" class="block text-sm font-bold text-primary">{{ __('leave.reason') }}</label><textarea id="leave_reason" name="reason" rows="3" class="control mt-2" required>{{ old('reason') }}</textarea></div>
                    <div><label for="leave_evidence" class="block text-sm font-bold text-primary">{{ __('leave.evidence') }}</label><input id="leave_evidence" name="evidence" type="file" accept=".pdf,.jpg,.jpeg,.png" class="control mt-2"><p class="mt-2 text-xs text-secondary">{{ __('leave.evidence_help') }}</p></div>
                    <x-button type="submit"><x-icon name="calendar" size="4" /> {{ __('leave.submit') }}</x-button>
                </form>
            @endif
        </article>

        <article class="surface-panel overflow-hidden rounded-2xl border shadow-panel">
            <div class="border-b border-slate-200 p-6 dark:border-slate-800"><h2 class="text-xl font-bold text-primary">{{ __('leave.history') }}</h2></div>
            <div class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse ($requests as $leaveRequest)
                    <div class="p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-bold text-primary">{{ $leaveRequest->type?->name }}</p><p class="mt-1 text-sm text-secondary">{{ $leaveRequest->start_date?->format('d M Y') }} — {{ $leaveRequest->end_date?->format('d M Y') }} · {{ $leaveRequest->total_days }} {{ __('leave.days') }}</p></div><x-badge variant="warning">{{ __('leave.statuses.'.$leaveRequest->requestStatus()->value) }}</x-badge></div>
                        @if ($leaveRequest->approvalInstance)<div class="mt-4 flex flex-wrap gap-2">@foreach ($leaveRequest->approvalInstance->steps as $step)<x-badge :variant="$step->stepStatus()->value === 'approved' ? 'success' : 'neutral'">{{ $step->step_order }}. {{ $step->name }} · {{ __('leave.step_statuses.'.$step->stepStatus()->value) }}</x-badge>@endforeach</div>@endif
                        @if ($leaveRequest->requestStatus()->isPending())<form method="POST" action="{{ route('leave.requests.cancel', $leaveRequest) }}" class="mt-4">@csrf @method('DELETE')<x-button type="submit" variant="danger">{{ __('leave.cancel') }}</x-button></form>@endif
                    </div>
                @empty <div class="p-8 text-center text-sm text-secondary">{{ __('leave.no_records') }}</div>@endforelse
            </div>
            <div class="p-5">{{ $requests->links() }}</div>
        </article>
    </section>
@endsection
