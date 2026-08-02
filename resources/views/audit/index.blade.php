@extends('layouts.app')

@section('title', __('auth.audit_log'))
@section('page-title', __('auth.audit_log'))

@section('content')
    <header class="mb-6">
        <p class="section-kicker">{{ __('auth.audit') }}</p>
        <h1 class="mt-2 text-3xl font-bold text-primary">{{ __('auth.audit_log') }}</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">{{ __('auth.audit_description') }}</p>
    </header>

    <div class="grid gap-6 2xl:grid-cols-2">
        <section class="surface-panel overflow-hidden rounded-2xl border shadow-panel" aria-labelledby="activity-title">
            <div class="border-b border-slate-200 p-5 dark:border-slate-700"><h2 id="activity-title" class="text-lg font-bold text-primary">{{ __('auth.activity_events') }}</h2></div>
            <div class="divide-y divide-slate-200 dark:divide-slate-700">
                @forelse ($activities as $activity)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-3"><p class="font-bold text-primary">{{ $activity->event ?? $activity->description }}</p><time class="whitespace-nowrap text-xs text-secondary">{{ $activity->created_at?->format('d M Y H:i') }}</time></div>
                        <p class="mt-2 text-sm text-secondary">{{ $activity->description }}</p>
                        <p class="mt-2 text-xs text-secondary">{{ __('auth.actor') }}: {{ $activity->causer?->name ?? __('auth.system') }}</p>
                    </article>
                @empty
                    <p class="p-5 text-sm text-secondary">{{ __('auth.no_audit_events') }}</p>
                @endforelse
            </div>
            <div class="border-t border-slate-200 p-5 dark:border-slate-700">{{ $activities->links() }}</div>
        </section>

        <section class="surface-panel overflow-hidden rounded-2xl border shadow-panel" aria-labelledby="authentication-title">
            <div class="border-b border-slate-200 p-5 dark:border-slate-700"><h2 id="authentication-title" class="text-lg font-bold text-primary">{{ __('auth.authentication_events') }}</h2></div>
            <div class="divide-y divide-slate-200 dark:divide-slate-700">
                @forelse ($authenticationEvents as $event)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-3"><p class="font-bold text-primary">{{ __('auth.events.'.$event->type) }}</p><time class="whitespace-nowrap text-xs text-secondary">{{ $event->occurred_at->format('d M Y H:i') }}</time></div>
                        <p class="mt-2 text-xs text-secondary">{{ __('auth.account') }}: {{ $event->user?->name ?? __('auth.unknown_account') }}</p>
                    </article>
                @empty
                    <p class="p-5 text-sm text-secondary">{{ __('auth.no_audit_events') }}</p>
                @endforelse
            </div>
            <div class="border-t border-slate-200 p-5 dark:border-slate-700">{{ $authenticationEvents->links() }}</div>
        </section>
    </div>
@endsection
