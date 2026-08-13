@extends('layouts.app')

@section('title', __('leave.review_queue'))
@section('page-title', __('leave.review_queue'))

@section('content')
    @if ($errors->any())<x-alert variant="danger" :title="__('ui.design.error_title')" class="mb-6"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
    <div><p class="section-kicker">{{ __('leave.eyebrow') }}</p><h1 class="mt-2 text-3xl font-bold text-primary">{{ __('leave.review_queue') }}</h1></div>
    <div class="mt-6 space-y-5">
        @forelse ($requests as $leaveRequest)
            @php($currentStep = $leaveRequest->approvalInstance?->steps?->firstWhere('step_order', $leaveRequest->approvalInstance?->current_step_order))
            <article class="surface-panel rounded-2xl border p-6 shadow-panel"><div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-xl font-bold text-primary">{{ $leaveRequest->employee?->full_name }} · {{ $leaveRequest->type?->name }}</h2><p class="mt-2 text-sm text-secondary">{{ $leaveRequest->start_date?->format('d M Y') }} — {{ $leaveRequest->end_date?->format('d M Y') }} · {{ $leaveRequest->total_days }} {{ __('leave.days') }}</p><p class="mt-3 rounded-xl bg-slate-50 p-3 text-sm text-secondary dark:bg-white/5">{{ $leaveRequest->reason }}</p></div><div class="text-right"><x-badge variant="warning">{{ __('leave.statuses.'.$leaveRequest->requestStatus()->value) }}</x-badge><p class="mt-2 text-xs text-secondary">{{ $currentStep?->name }}</p></div></div>
                @if ($leaveRequest->evidenceDocument)<a href="{{ route('leave.evidence.download', $leaveRequest) }}" class="mt-4 inline-flex text-sm font-bold text-brand-700 hover:underline dark:text-brand-300">{{ __('leave.download_evidence') }}</a>@endif
                <form method="POST" action="{{ route('leave.review.update', $leaveRequest) }}" class="mt-5 grid gap-4 lg:grid-cols-[13rem_1fr_auto]">@csrf @method('PUT')
                    <x-form.select name="decision" :label="__('leave.decision')"><option value="approve">{{ __('leave.approve') }}</option><option value="reject">{{ __('leave.reject') }}</option><option value="request_revision">{{ __('leave.request_revision') }}</option></x-form.select>
                    <x-form.input name="review_notes" :label="__('leave.review_notes')" required />
                    <div class="flex items-end"><x-button type="submit">{{ __('leave.review') }}</x-button></div>
                </form>
            </article>
        @empty <x-state-panel icon="inbox" :title="__('leave.no_records')" />@endforelse
    </div>
@endsection
