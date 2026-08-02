@extends('layouts.app')

@section('title', __('ess.request_detail'))
@section('page-title', __('ess.request_detail'))

@section('content')
    @if ($errors->any())<x-alert variant="danger" :title="__('ui.design.error_title')" class="mb-6"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
    @php($status = $changeRequest->status->value)
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="section-kicker">{{ $changeRequest->public_id }}</p><h1 class="mt-2 text-3xl font-bold text-primary">{{ __($changeRequest->type->labelKey()) }}</h1><p class="mt-2 text-sm text-secondary">{{ __('ess.submitted_at') }} {{ $changeRequest->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</p></div>
        <x-badge :variant="$status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : ($status === 'pending' ? 'warning' : 'neutral'))" class="self-start sm:self-auto">{{ __('ess.statuses.'.$status) }}</x-badge>
    </div>

    @if ($changeRequest->manual_follow_up_required && $status === 'approved')<x-alert variant="warning" :title="__('ess.statuses.approved')" class="mt-6">{{ __('ess.manual_follow_up') }}</x-alert>@endif

    <section class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach ([__('ess.old_value') => $currentValues, __('ess.new_value') => $proposedValues] as $title => $values)
            <article class="surface-panel rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ $title }}</h2><dl class="mt-4 divide-y divide-slate-200 text-sm dark:divide-slate-800">
                @forelse ($values as $key => $value)
                    <div class="py-3"><dt class="font-medium text-secondary">{{ __('ess.fields.'.$key) }}</dt><dd class="mt-1 break-words font-bold text-primary">{{ is_array($value) ? __('ess.record_count', ['count' => count($value)]) : ($value === null || $value === '' ? '—' : $value) }}</dd></div>
                @empty<div class="py-4 text-secondary">{{ __('ess.not_available') }}</div>@endforelse
            </dl></article>
        @endforeach
    </section>

    <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('ess.reason') }}</h2><p class="mt-3 whitespace-pre-line text-sm leading-6 text-secondary">{{ $changeRequest->reason }}</p>
        @if ($changeRequest->attachmentDocument)<a href="{{ route('employee-documents.download', $changeRequest->attachmentDocument) }}" class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-brand-700 dark:text-brand-300"><x-icon name="lock" size="4" />{{ __('ess.download_attachment') }}</a>@endif
        @if ($changeRequest->review_notes)<div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800"><h3 class="font-bold text-primary">{{ __('ess.review_notes') }}</h3><p class="mt-2 whitespace-pre-line text-sm text-secondary">{{ $changeRequest->review_notes }}</p><p class="mt-2 text-xs text-secondary">{{ $changeRequest->reviewer?->name }} · {{ $changeRequest->reviewed_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</p></div>@endif
    </section>

    @if ($canReview)
        <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel"><h2 class="text-xl font-bold text-primary">{{ __('ess.review_request') }}</h2><form method="POST" action="{{ route('ess.review.update', $changeRequest) }}" class="mt-5 space-y-5">@csrf @method('PUT')
            <x-form.select name="decision" :label="__('ess.decision')" required><option value="approve">{{ __('ess.approve') }}</option><option value="reject">{{ __('ess.reject') }}</option></x-form.select>
            <div><label for="review_notes" class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('ess.review_notes') }}</label><textarea id="review_notes" name="review_notes" rows="4" class="control mt-2" required>{{ old('review_notes') }}</textarea></div>
            <x-button type="submit">{{ __('ess.review_request') }}</x-button>
        </form></section>
    @endif

    @if ($canCancel)
        <form method="POST" action="{{ route('ess.requests.cancel', $changeRequest) }}" class="mt-6">@csrf @method('DELETE')<x-button type="submit" variant="danger">{{ __('ess.cancel_request') }}</x-button></form>
    @endif
@endsection
