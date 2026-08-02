@extends('layouts.guest')

@section('title', __('auth.verify_email'))

@section('content')
    <div class="surface-panel rounded-2xl border p-6 shadow-panel sm:p-8">
        <h2 class="text-2xl font-bold text-primary">{{ __('auth.verify_email') }}</h2>
        <p class="mt-3 text-sm leading-6 text-secondary">{{ __('auth.verify_description') }}</p>
        <form method="POST" action="{{ route('verification.send') }}" class="mt-7">
            @csrf
            <x-button type="submit" class="w-full">{{ __('auth.resend_verification') }}</x-button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <x-button type="submit" variant="ghost" class="w-full">{{ __('auth.logout') }}</x-button>
        </form>
    </div>
@endsection
