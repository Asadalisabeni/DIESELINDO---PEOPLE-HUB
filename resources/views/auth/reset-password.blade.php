@extends('layouts.guest')

@section('title', __('auth.reset_password'))

@section('content')
    <div class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-8">
        <h2 class="text-2xl font-bold text-primary">{{ __('auth.reset_password') }}</h2>
        <p class="mt-2 text-sm leading-6 text-secondary">{{ __('auth.password_policy') }}</p>
        <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">
            <x-form.input name="email" type="email" :label="__('auth.email')" :error="$errors->first('email')" :value="old('email', request('email'))" autocomplete="username" required />
            <x-form.input name="password" type="password" :label="__('auth.new_password')" :error="$errors->first('password')" autocomplete="new-password" required />
            <x-form.input name="password_confirmation" type="password" :label="__('auth.confirm_password')" autocomplete="new-password" required />
            <x-button type="submit" class="w-full">{{ __('auth.reset_password') }}</x-button>
        </form>
    </div>
@endsection
