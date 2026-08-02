@extends('layouts.guest')

@section('title', __('auth.two_factor_challenge'))

@section('content')
    <div x-data="{ recovery: false }" class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-8">
        <h2 class="text-2xl font-bold text-primary">{{ __('auth.two_factor_challenge') }}</h2>
        <p class="mt-2 text-sm leading-6 text-secondary" x-text="recovery ? @js(__('auth.recovery_description')) : @js(__('auth.code_description'))"></p>
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-7 space-y-5">
            @csrf
            <div x-show="! recovery">
                <x-form.input name="code" type="text" :label="__('auth.authenticator_code')" :error="$errors->first('code')" inputmode="numeric" autocomplete="one-time-code" autofocus />
            </div>
            <div x-show="recovery" x-cloak>
                <x-form.input name="recovery_code" type="text" :label="__('auth.recovery_code')" :error="$errors->first('recovery_code')" autocomplete="one-time-code" />
            </div>
            <x-button type="submit" class="w-full">{{ __('auth.continue') }}</x-button>
        </form>
        <button type="button" x-on:click="recovery = ! recovery" class="mt-5 text-sm font-bold text-brand-700 dark:text-brand-400" x-text="recovery ? @js(__('auth.use_authenticator')) : @js(__('auth.use_recovery_code'))"></button>
    </div>
@endsection
