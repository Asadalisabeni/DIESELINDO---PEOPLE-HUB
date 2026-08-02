@extends('layouts.app')

@section('title', __('auth.access_management'))
@section('page-title', __('auth.access_management'))

@section('content')
    @if (session('status'))
        <x-alert variant="success" :title="__('auth.status')" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <header class="mb-6">
        <p class="section-kicker">{{ __('auth.identity_access') }}</p>
        <h1 class="mt-2 text-3xl font-bold text-primary">{{ __('auth.access_management') }}</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-secondary">{{ __('auth.access_description') }}</p>
    </header>

    <section class="surface-panel rounded-2xl border p-6 shadow-panel" aria-labelledby="provision-title">
        <h2 id="provision-title" class="text-xl font-bold text-primary">{{ __('auth.provision_user') }}</h2>
        <p class="mt-2 text-sm text-secondary">{{ __('auth.provision_description') }}</p>
        <form method="POST" action="{{ route('iam.users.store') }}" class="mt-5 grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">
            @csrf
            <x-form.input name="name" :label="__('auth.full_name')" :error="$errors->first('name')" :value="old('name')" required />
            <x-form.input name="email" type="email" :label="__('auth.email')" :error="$errors->first('email')" :value="old('email')" required />
            <div>
                <label for="role" class="block text-sm font-bold text-primary">{{ __('auth.role') }}</label>
                <select id="role" name="role" class="control mt-2" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit">{{ __('auth.provision') }}</x-button>
        </form>
    </section>

    <section class="surface-panel mt-6 overflow-hidden rounded-2xl border shadow-panel" aria-labelledby="users-title">
        <div class="border-b border-slate-200 p-6 dark:border-slate-700">
            <h2 id="users-title" class="text-xl font-bold text-primary">{{ __('auth.users_and_roles') }}</h2>
        </div>
        <div class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach ($users as $managedUser)
                <form method="POST" action="{{ route('iam.users.update', $managedUser) }}" class="grid gap-4 p-6 xl:grid-cols-[1.2fr_2fr_auto] xl:items-center">
                    @csrf
                    @method('PUT')
                    <div>
                        <p class="font-bold text-primary">{{ $managedUser->name }}</p>
                        <p class="mt-1 text-sm text-secondary">{{ $managedUser->email }}</p>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        @foreach ($roles as $role)
                            <label class="flex items-center gap-2 text-xs text-secondary">
                                <input type="checkbox" name="roles[]" value="{{ $role }}" @checked($managedUser->hasRole($role)) class="size-4 rounded border-slate-300 text-brand-600">
                                {{ $role }}
                            </label>
                        @endforeach
                        <label class="flex items-center gap-2 text-xs font-bold text-primary">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($managedUser->is_active) class="size-4 rounded border-slate-300 text-brand-600">
                            {{ __('auth.active_account') }}
                        </label>
                    </div>
                    <x-button type="submit" variant="secondary">{{ __('ui.actions.save') }}</x-button>
                </form>
            @endforeach
        </div>
        <div class="border-t border-slate-200 p-5 dark:border-slate-700">{{ $users->links() }}</div>
    </section>
@endsection
