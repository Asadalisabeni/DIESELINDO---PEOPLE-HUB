@extends('layouts.guest')

@section('title', __('auth.login'))

@section('content')
    <div class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-8">
        <p class="section-kicker">{{ __('auth.secure_workspace') }}</p>
        <h2 class="mt-2 text-2xl font-bold text-primary">{{ __('auth.login') }}</h2>
        <p class="mt-2 text-sm leading-6 text-secondary">{{ __('auth.login_description') }}</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
            @csrf
            <x-form.input name="email" type="email" :label="__('auth.email')" :error="$errors->first('email')" :value="old('email')" autocomplete="username" required autofocus />
            <x-form.input name="password" type="password" :label="__('auth.password_label')" :error="$errors->first('password')" autocomplete="current-password" required />

            <div class="flex items-center justify-between gap-4">
                <label class="flex items-center gap-2 text-sm text-secondary">
                    <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('auth.remember_me') }}
                </label>
                <a href="{{ route('password.request') }}" class="text-sm font-bold text-brand-700 hover:text-brand-800 dark:text-brand-400">{{ __('auth.forgot_password') }}</a>
            </div>

            <x-button type="submit" class="w-full">{{ __('auth.login') }}</x-button>
        </form>
    </div>
@endsection
