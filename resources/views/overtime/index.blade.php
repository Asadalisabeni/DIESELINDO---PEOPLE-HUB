@extends('layouts.app')

@section('title', __('overtime.title'))
@section('page-title', __('overtime.title'))

@section('content')
    @if ($errors->any())<x-alert variant="danger" :title="__('ui.design.error_title')" class="mb-6"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
    <section class="overflow-hidden rounded-3xl bg-navy-950 px-6 py-9 text-white shadow-panel sm:px-9" aria-labelledby="overtime-title">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-brand-400">{{ __('overtime.eyebrow') }}</p>
        <h1 id="overtime-title" class="mt-3 text-3xl font-bold">{{ $employee->full_name }}</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">{{ __('overtime.description') }}</p>
        <div class="mt-6 flex flex-wrap gap-2"><x-badge variant="brand">{{ $employee->employee_number }}</x-badge><x-badge variant="success">{{ $employee->legalEntity?->display_name }}</x-badge></div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
        <article class="surface-panel rounded-2xl border p-6 shadow-panel">
            <h2 class="text-xl font-bold text-primary">{{ __('overtime.request_overtime') }}</h2>
            <form method="POST" action="{{ route('overtime.requests.store') }}" class="mt-5 space-y-5">@csrf
                @if($team->isNotEmpty())
                    <x-form.select name="employee_public_id" :label="__('overtime.employee')"><option value="">{{ $employee->full_name }} (self)</option>@foreach($team as $member)<option value="{{ $member->public_id }}">{{ $member->employee_number }} · {{ $member->full_name }}</option>@endforeach</x-form.select>
                @endif
                <x-form.select name="request_type" :label="__('overtime.request_type')" required>@foreach(__('overtime.types') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-form.select>
                <div class="grid gap-5 sm:grid-cols-2"><x-form.input name="planned_start" type="datetime-local" :label="__('overtime.planned_start')" :value="old('planned_start')" required /><x-form.input name="planned_end" type="datetime-local" :label="__('overtime.planned_end')" :value="old('planned_end')" required /></div>
                <div><label for="overtime_reason" class="block text-sm font-bold text-primary">{{ __('overtime.reason') }}</label><textarea id="overtime_reason" name="reason" rows="3" class="control mt-2" required>{{ old('reason') }}</textarea></div>
                <div><label for="overtime_work_description" class="block text-sm font-bold text-primary">{{ __('overtime.work_description') }}</label><textarea id="overtime_work_description" name="work_description" rows="4" class="control mt-2" required>{{ old('work_description') }}</textarea></div>
                <x-button type="submit"><x-icon name="clock" size="4" /> {{ __('overtime.submit') }}</x-button>
            </form>
        </article>

        <article class="surface-panel overflow-hidden rounded-2xl border shadow-panel">
            <div class="border-b border-slate-200 p-6 dark:border-slate-800"><h2 class="text-xl font-bold text-primary">{{ __('overtime.history') }}</h2></div>
            <div class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse($requests as $overtimeRequest)
                    <div class="p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-bold text-primary">{{ $overtimeRequest->employee?->full_name }} · {{ __('overtime.day_types.'.$overtimeRequest->day_type_snapshot->value) }}</p><p class="mt-1 text-sm text-secondary">{{ $overtimeRequest->plannedStartAt()->timezone('Asia/Jakarta')->format('d M Y H:i') }}—{{ $overtimeRequest->plannedEndAt()->timezone('Asia/Jakarta')->format('H:i') }} · {{ $overtimeRequest->planned_minutes }} {{ __('overtime.planned_minutes') }}</p></div><x-badge variant="warning">{{ __('overtime.statuses.'.$overtimeRequest->requestStatus()->value) }}</x-badge></div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4"><div><span class="text-secondary">{{ __('overtime.approved_minutes') }}</span><strong class="block text-primary">{{ $overtimeRequest->approved_minutes ?? '—' }}</strong></div><div><span class="text-secondary">{{ __('overtime.actual_minutes') }}</span><strong class="block text-primary">{{ $overtimeRequest->actual_minutes ?? '—' }}</strong></div><div><span class="text-secondary">{{ __('overtime.payable_minutes') }}</span><strong class="block text-primary">{{ $overtimeRequest->payable_minutes ?? '—' }}</strong></div><div><span class="text-secondary">{{ __('overtime.payroll_period') }}</span><strong class="block text-primary">{{ $overtimeRequest->payroll_period_key ?? '—' }}</strong></div></div>
                        @if($overtimeRequest->approvalInstance)<div class="mt-4 flex flex-wrap gap-2">@foreach($overtimeRequest->approvalInstance->steps as $step)<x-badge :variant="$step->stepStatus()->value === 'approved' ? 'success' : 'neutral'">{{ $step->step_order }}. {{ $step->name }} · {{ $step->stepStatus()->value }}</x-badge>@endforeach</div>@endif
                        @if(in_array($overtimeRequest->requestStatus()->value, ['pending_manager', 'approved_waiting_actual'], true) && now()->lessThan($overtimeRequest->plannedStartAt()))<form method="POST" action="{{ route('overtime.requests.cancel', $overtimeRequest) }}" class="mt-4">@csrf @method('DELETE')<x-button type="submit" variant="danger">{{ __('overtime.cancel') }}</x-button></form>@endif
                    </div>
                @empty <div class="p-8 text-center text-sm text-secondary">{{ __('overtime.no_records') }}</div>@endforelse
            </div><div class="p-5">{{ $requests->links() }}</div>
        </article>
    </section>
@endsection
