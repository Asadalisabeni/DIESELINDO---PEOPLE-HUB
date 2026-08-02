@extends('layouts.guest')

@section('title', __('auth.confirm_password'))

@section('content')
    <div class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-8">
        <h2 class="text-2xl font-bold text-primary">{{ __('auth.confirm_password') }}</h2>
        <p class="mt-2 text-sm leading-6 text-secondary">{{ __('auth.confirm_sensitive_action') }}</p>
        <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-7 space-y-5">
            @csrf
            <x-form.input name="password" type="password" :label="__('auth.password_label')" :error="$errors->first('password')" autocomplete="current-password" required autofocus />
            <x-button type="submit" class="w-full">{{ __('auth.confirm') }}</x-button>
        </form>
    </div>
@endsection
