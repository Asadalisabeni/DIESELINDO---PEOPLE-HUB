@extends('layouts.app')

@section('title', __('ess.review_queue'))
@section('page-title', __('ess.review_queue'))

@section('content')
    <div><p class="section-kicker">{{ __('ess.eyebrow') }}</p><h1 class="mt-2 text-3xl font-bold text-primary">{{ __('ess.review_queue') }}</h1><p class="mt-2 text-sm text-secondary">{{ __('ess.review_queue_help') }}</p></div>
    <div class="mt-6 grid gap-4">
        @forelse ($changeRequests as $changeRequest)
            <a href="{{ route('ess.requests.show', $changeRequest) }}" class="surface-panel flex flex-col gap-4 rounded-2xl border p-5 shadow-panel transition hover:border-brand-300 sm:flex-row sm:items-center sm:justify-between dark:hover:border-brand-700">
                <span><span class="block font-bold text-primary">{{ $changeRequest->employee?->full_name }} · {{ __($changeRequest->type->labelKey()) }}</span><span class="mt-1 block text-sm text-secondary">{{ $changeRequest->legalEntity?->display_name }} · {{ $changeRequest->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</span></span>
                <x-badge variant="warning">{{ __('ess.statuses.pending') }}</x-badge>
            </a>
        @empty
            <x-state-panel icon="inbox" :title="__('ess.no_review_requests')" />
        @endforelse
    </div>
    <div class="mt-5">{{ $changeRequests->links() }}</div>
@endsection
