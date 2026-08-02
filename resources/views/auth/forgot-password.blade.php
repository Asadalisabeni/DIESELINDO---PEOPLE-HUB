@extends('layouts.guest')

@section('title', __('auth.forgot_password'))

@section('content')
    <div class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-8">
        <h2 class="text-2xl font-bold text-primary">{{ __('auth.forgot_password') }}</h2>
        <p class="mt-2 text-sm leading-6 text-secondary">{{ __('auth.forgot_description') }}</p>
        <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5">
            @csrf
            <x-form.input name="email" type="email" :label="__('auth.email')" :error="$errors->first('email')" :value="old('email')" autocomplete="email" required autofocus />
            <x-button type="submit" class="w-full">{{ __('auth.send_reset_link') }}</x-button>
        </form>
        <a href="{{ route('login') }}" class="mt-5 inline-block text-sm font-bold text-brand-700 dark:text-brand-400">{{ __('auth.back_to_login') }}</a>
    </div>
@endsection
