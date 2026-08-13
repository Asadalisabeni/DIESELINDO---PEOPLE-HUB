@extends('layouts.app')

@section('title', __('overtime.review_queue'))
@section('page-title', __('overtime.review_queue'))

@section('content')
    @if ($errors->any())<x-alert variant="danger" :title="__('ui.design.error_title')" class="mb-6"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
    <div><p class="section-kicker">{{ __('overtime.eyebrow') }}</p><h1 class="mt-2 text-3xl font-bold text-primary">{{ __('overtime.review_queue') }}</h1></div>
    <div class="mt-6 space-y-5">
        @forelse($requests as $overtimeRequest)
            @php($currentStep = $overtimeRequest->approvalInstance?->steps?->firstWhere('step_order', $overtimeRequest->approvalInstance?->current_step_order))
            <article class="surface-panel rounded-2xl border p-6 shadow-panel">
                <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-xl font-bold text-primary">{{ $overtimeRequest->employee?->full_name }} · {{ __('overtime.day_types.'.$overtimeRequest->day_type_snapshot->value) }}</h2><p class="mt-2 text-sm text-secondary">{{ $overtimeRequest->plannedStartAt()->timezone('Asia/Jakarta')->format('d M Y H:i') }}—{{ $overtimeRequest->plannedEndAt()->timezone('Asia/Jakarta')->format('H:i') }} · {{ $overtimeRequest->planned_minutes }} {{ __('overtime.planned_minutes') }}</p><p class="mt-3 rounded-xl bg-slate-50 p-3 text-sm text-secondary dark:bg-white/5">{{ $overtimeRequest->reason }}<br>{{ $overtimeRequest->work_description }}</p></div><div class="text-right"><x-badge variant="warning">{{ __('overtime.statuses.'.$overtimeRequest->requestStatus()->value) }}</x-badge><p class="mt-2 text-xs text-secondary">{{ $currentStep?->name }}</p></div></div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4"><div>{{ __('overtime.planned_minutes') }}<strong class="block text-primary">{{ $overtimeRequest->planned_minutes }}</strong></div><div>{{ __('overtime.approved_minutes') }}<strong class="block text-primary">{{ $overtimeRequest->approved_minutes ?? '—' }}</strong></div><div>{{ __('overtime.actual_minutes') }}<strong class="block text-primary">{{ $overtimeRequest->actual_minutes ?? '—' }}</strong></div><div>{{ __('overtime.payable_minutes') }}<strong class="block text-primary">{{ $overtimeRequest->payable_minutes ?? '—' }}</strong></div></div>
                <form method="POST" action="{{ route('overtime.review.update', $overtimeRequest) }}" class="mt-5 grid gap-4 lg:grid-cols-2">@csrf @method('PUT')
                    <x-form.select name="decision" :label="__('overtime.decision')"><option value="approve">{{ __('overtime.approve') }}</option><option value="reject">{{ __('overtime.reject') }}</option><option value="request_revision">{{ __('overtime.request_revision') }}</option></x-form.select>
                    @if((int)$currentStep?->step_order === 1)<x-form.input name="approved_minutes" type="number" min="1" :max="$overtimeRequest->planned_minutes" :label="__('overtime.approved_minutes')" :value="$overtimeRequest->planned_minutes" />@endif
                    @if((int)$currentStep?->step_order === 3)<x-form.input name="payroll_period_key" type="month" :label="__('overtime.payroll_period')" required />@endif
                    <div class="lg:col-span-2"><x-form.input name="review_notes" :label="__('overtime.review_notes')" required /></div>
                    <div class="lg:col-span-2"><x-button type="submit">{{ __('overtime.review') }}</x-button></div>
                </form>
            </article>
        @empty <x-state-panel icon="inbox" :title="__('overtime.no_records')" />@endforelse
    </div>
@endsection
