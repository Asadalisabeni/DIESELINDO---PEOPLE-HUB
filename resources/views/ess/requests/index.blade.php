@extends('layouts.app')

@section('title', __('ess.request_history'))
@section('page-title', __('ess.request_history'))

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="section-kicker">{{ __('ess.eyebrow') }}</p><h1 class="mt-2 text-3xl font-bold text-primary">{{ __('ess.request_history') }}</h1><p class="mt-2 text-sm text-secondary">{{ __('ess.new_request_help') }}</p></div>
        <x-button :href="route('ess.dashboard')" variant="secondary">{{ __('ess.my_profile') }}</x-button>
    </div>

    <div class="surface-panel mt-6 overflow-hidden rounded-2xl border shadow-panel">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5"><tr><th class="px-5 py-3">{{ __('ess.fields.request_type') }}</th><th class="px-5 py-3">{{ __('employee.status') }}</th><th class="px-5 py-3">{{ __('ess.submitted_at') }}</th><th class="px-5 py-3"><span class="sr-only">Detail</span></th></tr></thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($changeRequests as $changeRequest)
                        @php($status = $changeRequest->status->value)
                        <tr><td class="px-5 py-4 font-bold text-primary">{{ __($changeRequest->type->labelKey()) }}</td><td class="px-5 py-4"><x-badge :variant="$status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : ($status === 'pending' ? 'warning' : 'neutral'))">{{ __('ess.statuses.'.$status) }}</x-badge></td><td class="px-5 py-4 text-secondary">{{ $changeRequest->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td><td class="px-5 py-4 text-right"><a href="{{ route('ess.requests.show', $changeRequest) }}" class="font-bold text-brand-700 dark:text-brand-300">{{ __('ui.actions.learn_more') }}</a></td></tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-secondary">{{ __('ess.no_requests') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $changeRequests->links() }}</div>
    </div>
@endsection
