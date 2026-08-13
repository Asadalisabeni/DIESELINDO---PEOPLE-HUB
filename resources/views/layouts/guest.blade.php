<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#101b2d">
        <title>@yield('title') — {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full font-sans antialiased">
        <a href="#main-content" class="sr-only rounded-lg bg-white px-4 py-2.5 font-bold text-navy-950 shadow-overlay focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50">
            {{ __('ui.a11y.skip_to_content') }}
        </a>

        <div class="grid min-h-screen lg:grid-cols-[1.05fr_.95fr]">
            <section class="relative hidden overflow-hidden bg-navy-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="pointer-events-none absolute -right-32 -top-32 size-96 rounded-full border-[72px] border-brand-500/10"></div>
                <x-brand />
                <div class="relative max-w-xl">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-400">{{ __('auth.secure_workspace') }}</p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight">{{ __('auth.guest_title') }}</h1>
                    <p class="mt-5 text-base leading-7 text-slate-300">{{ __('auth.guest_description') }}</p>
                </div>
                <p class="text-xs text-slate-500">{{ __('auth.authorized_only') }}</p>
            </section>

            <main id="main-content" tabindex="-1" class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8">
                <div class="w-full max-w-md">
                    <div class="mb-8 flex items-center justify-between lg:justify-end">
                        <div class="lg:hidden"><x-brand /></div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('locale.update') }}">
                                @csrf
                                <label for="guest-locale" class="sr-only">{{ __('ui.topbar.language') }}</label>
                                <select id="guest-locale" name="locale" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm font-bold text-slate-700 dark:border-slate-700 dark:bg-navy-950 dark:text-slate-200">
                                    <option value="id" @selected(app()->getLocale() === 'id')>ID</option>
                                    <option value="en" @selected(app()->getLocale() === 'en')>EN</option>
                                </select>
                            </form>
                            <button type="button" x-data x-on:click="$store.theme.toggle()" class="rounded-lg border border-slate-200 p-2 text-slate-600 dark:border-slate-700 dark:text-slate-300" aria-label="{{ __('ui.theme.toggle') }}">
                                <x-icon name="moon" />
                            </button>
                        </div>
                    </div>

                    @if (session('status'))
                        <x-alert variant="success" :title="__('auth.status')" class="mb-5">{{ session('status') }}</x-alert>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>
