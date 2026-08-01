<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">

        <title>@hasSection('title')@yield('title') — @endif{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
        <a href="#main-content" class="sr-only rounded-md bg-white px-4 py-2 text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50">
            Lewati ke konten utama
        </a>

        <header class="border-b border-slate-200 bg-white" aria-label="Header aplikasi">
            <div class="mx-auto flex h-16 max-w-7xl items-center px-6 lg:px-8">
                <a href="{{ url('/') }}" class="font-semibold tracking-tight text-slate-950">
                    {{ config('app.name') }}
                </a>
            </div>
        </header>

        <main id="main-content" tabindex="-1">
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-7xl px-6 py-5 text-sm text-slate-500 lg:px-8">
                &copy; {{ now()->year }} PT Dieselindo Utama Nusa
            </div>
        </footer>
    </body>
</html>
