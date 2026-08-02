@extends('layouts.app')

@section('title', __('auth.recovery_codes'))
@section('page-title', __('auth.recovery_codes'))

@section('content')
    <section class="surface-panel mx-auto max-w-2xl rounded-2xl border p-6 shadow-panel sm:p-8" aria-labelledby="recovery-title">
        <p class="section-kicker">{{ __('auth.two_factor') }}</p>
        <h1 id="recovery-title" class="mt-2 text-3xl font-bold text-primary">{{ __('auth.recovery_codes') }}</h1>
        <p class="mt-3 text-sm leading-6 text-secondary">{{ __('auth.recovery_codes_warning') }}</p>

        <ul class="mt-6 grid grid-cols-1 gap-2 rounded-xl bg-slate-100 p-5 font-mono text-sm text-primary sm:grid-cols-2 dark:bg-navy-950">
            @foreach ($recoveryCodes as $code)
                <li>{{ $code }}</li>
            @endforeach
        </ul>

        <div class="mt-6 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                @csrf
                <x-button type="submit" variant="secondary">{{ __('auth.regenerate_codes') }}</x-button>
            </form>
            <x-button :href="route('security.index')" variant="ghost">{{ __('ui.actions.go_back') }}</x-button>
        </div>
    </section>
@endsection
