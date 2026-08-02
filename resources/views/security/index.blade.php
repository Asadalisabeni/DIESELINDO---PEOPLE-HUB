@extends('layouts.app')

@section('title', __('auth.security'))
@section('page-title', __('auth.security'))

@section('content')
    @if (session('status'))
        <x-alert variant="success" :title="__('auth.status')" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <header class="mb-6">
        <p class="section-kicker">{{ __('auth.identity_access') }}</p>
        <h1 class="mt-2 text-3xl font-bold text-primary">{{ __('auth.security_title') }}</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">{{ __('auth.security_description') }}</p>
    </header>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="surface-panel rounded-2xl border p-6 shadow-panel" aria-labelledby="password-title">
            <h2 id="password-title" class="text-xl font-bold text-primary">{{ __('auth.change_password') }}</h2>
            <p class="mt-2 text-sm leading-6 text-secondary">{{ __('auth.password_policy') }}</p>
            <form method="POST" action="{{ route('user-password.update') }}" class="mt-6 space-y-4">
                @csrf
                @method('PUT')
                <x-form.input name="current_password" type="password" :label="__('auth.current_password')" :error="$errors->updatePassword->first('current_password')" autocomplete="current-password" required />
                <x-form.input name="password" type="password" :label="__('auth.new_password')" :error="$errors->updatePassword->first('password')" autocomplete="new-password" required />
                <x-form.input name="password_confirmation" type="password" :label="__('auth.confirm_password')" autocomplete="new-password" required />
                <x-button type="submit">{{ __('ui.actions.save') }}</x-button>
            </form>
        </section>

        <section class="surface-panel rounded-2xl border p-6 shadow-panel" aria-labelledby="two-factor-title">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="two-factor-title" class="text-xl font-bold text-primary">{{ __('auth.two_factor') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-secondary">{{ __('auth.two_factor_description') }}</p>
                </div>
                <x-badge :variant="$user->hasEnabledTwoFactorAuthentication() ? 'success' : 'neutral'">
                    {{ $user->hasEnabledTwoFactorAuthentication() ? __('auth.enabled') : __('auth.disabled') }}
                </x-badge>
            </div>

            @if (is_null($user->two_factor_secret))
                <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-6">
                    @csrf
                    <x-button type="submit">{{ __('auth.enable_two_factor') }}</x-button>
                </form>
            @elseif (! $user->hasEnabledTwoFactorAuthentication())
                <div class="mt-6 rounded-xl bg-white p-4 [&_svg]:mx-auto [&_svg]:max-w-full">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <p class="mt-4 text-sm text-secondary">{{ __('auth.scan_qr') }}</p>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 space-y-4">
                    @csrf
                    <x-form.input name="code" type="text" :label="__('auth.authenticator_code')" :error="$errors->confirmTwoFactorAuthentication->first('code')" inputmode="numeric" autocomplete="one-time-code" required />
                    <x-button type="submit">{{ __('auth.confirm_two_factor') }}</x-button>
                </form>
            @else
                <div class="mt-6">
                    <x-button :href="route('security.recovery-codes')" variant="secondary">{{ __('auth.show_recovery_codes') }}</x-button>
                </div>
                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger">{{ __('auth.disable_two_factor') }}</x-button>
                </form>
            @endif
        </section>
    </div>

    <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel" aria-labelledby="sessions-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="sessions-title" class="text-xl font-bold text-primary">{{ __('auth.active_sessions') }}</h2>
                <p class="mt-2 text-sm leading-6 text-secondary">{{ __('auth.sessions_description') }}</p>
            </div>
        </div>

        <div class="mt-5 divide-y divide-slate-200 dark:divide-slate-700">
            @forelse ($sessions as $session)
                <div class="grid gap-2 py-4 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div>
                        <p class="text-sm font-bold text-primary">{{ $session['device'] }}</p>
                        <p class="mt-1 text-xs text-secondary">{{ $session['ip_address'] }} · {{ $session['last_active_at']->diffForHumans() }}</p>
                    </div>
                    @if ($session['is_current'])
                        <x-badge variant="success">{{ __('auth.current_session') }}</x-badge>
                    @endif
                </div>
            @empty
                <p class="py-5 text-sm text-secondary">{{ __('auth.no_sessions') }}</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('security.sessions.destroy-others') }}" class="mt-6 grid gap-4 border-t border-slate-200 pt-6 sm:grid-cols-[1fr_auto] sm:items-end dark:border-slate-700">
            @csrf
            @method('DELETE')
            <x-form.input name="current_password" type="password" :label="__('auth.current_password')" :error="$errors->first('current_password')" autocomplete="current-password" required />
            <x-button type="submit" variant="danger">{{ __('auth.logout_other_sessions') }}</x-button>
        </form>
    </section>

    <section class="surface-panel mt-6 rounded-2xl border p-6 shadow-panel" aria-labelledby="history-title">
        <h2 id="history-title" class="text-xl font-bold text-primary">{{ __('auth.login_history') }}</h2>
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase tracking-wider text-secondary">
                    <tr><th class="pb-3 pr-5">{{ __('auth.event') }}</th><th class="pb-3">{{ __('auth.time') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($loginHistory as $event)
                        <tr><td class="py-3 pr-5 font-medium text-primary">{{ __('auth.events.'.$event->type) }}</td><td class="py-3 text-secondary">{{ $event->occurred_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="py-5 text-secondary">{{ __('auth.no_history') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
