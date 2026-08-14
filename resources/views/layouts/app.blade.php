<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#101b2d">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@hasSection('title')@yield('title') — @endif{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        x-data="{ sidebarOpen: false, desktop: window.matchMedia('(min-width: 1024px)').matches }"
        x-init="const media = window.matchMedia('(min-width: 1024px)'); media.addEventListener('change', event => { desktop = event.matches; if (desktop) sidebarOpen = false })"
        x-on:keydown.escape.window="sidebarOpen = false"
        class="min-h-full font-sans antialiased"
    >
        <a href="#main-content" class="sr-only rounded-lg bg-white px-4 py-2.5 font-bold text-navy-950 shadow-overlay focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70]">
            {{ __('ui.a11y.skip_to_content') }}
        </a>

        <div x-show="sidebarOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-navy-950/70 backdrop-blur-sm lg:hidden" x-on:click="sidebarOpen = false"></div>

        <aside
            id="application-sidebar"
            class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-navy-950 text-slate-200 shadow-overlay transition-transform duration-300 lg:translate-x-0 lg:shadow-none"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            :aria-hidden="desktop || sidebarOpen ? 'false' : 'true'"
            :inert="! desktop && ! sidebarOpen"
        >
            <div class="flex h-20 shrink-0 items-center justify-between border-b border-white/10 px-5">
                <a href="{{ route('home') }}" aria-label="{{ __('ui.app.name') }}">
                    <x-brand />
                </a>
                <button type="button" x-on:click="sidebarOpen = false" class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" aria-label="{{ __('ui.a11y.close_navigation') }}">
                    <x-icon name="x" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-5" aria-label="{{ __('ui.a11y.primary_navigation') }}">
                <p class="px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">{{ __('ui.nav.workspace') }}</p>
                <ul class="mt-2 space-y-1">
                    <li>
                        <a href="{{ route('home') }}" @class([
                            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                            'bg-white/10 text-white shadow-sm' => request()->routeIs('home'),
                            'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('home'),
                        ]) @if(request()->routeIs('home')) aria-current="page" @endif>
                            <x-icon name="home" />
                            <span>{{ __('ui.nav.overview') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('design-system') }}" @class([
                            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                            'bg-white/10 text-white shadow-sm' => request()->routeIs('design-system'),
                            'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('design-system'),
                        ]) @if(request()->routeIs('design-system')) aria-current="page" @endif>
                            <x-icon name="grid" />
                            <span>{{ __('ui.nav.design_system') }}</span>
                        </a>
                    </li>
                </ul>

                <p class="mt-7 px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">{{ __('ui.nav.people') }}</p>
                <ul class="mt-2 space-y-1">
                    @if (auth()->user()->employee && auth()->user()->can('viewSelfService', auth()->user()->employee))
                        <li>
                            <a href="{{ route('ess.dashboard') }}" @class([
                                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                'bg-white/10 text-white shadow-sm' => request()->routeIs('ess.dashboard', 'ess.requests.*'),
                                'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('ess.dashboard', 'ess.requests.*'),
                            ])>
                                <x-icon name="home" />
                                <span>{{ __('ui.nav.self_service') }}</span>
                            </a>
                        </li>
                    @endif
                    @can('employees.view')
                        <li>
                            <a href="{{ route('employees.index') }}" @class([
                                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                'bg-white/10 text-white shadow-sm' => request()->routeIs('employees.*'),
                                'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('employees.*'),
                            ])>
                                <x-icon name="users" />
                                <span>{{ __('ui.nav.employees') }}</span>
                            </a>
                        </li>
                    @endcan
                    @can('attendance.access')
                        @if (auth()->user()->employee)
                            <li>
                                <a href="{{ route('attendance.index') }}" @class([
                                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                    'bg-white/10 text-white shadow-sm' => request()->routeIs('attendance.index', 'attendance.corrections.*'),
                                    'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('attendance.index', 'attendance.corrections.*'),
                                ])><x-icon name="clock" /><span>{{ __('ui.nav.attendance') }}</span></a>
                            </li>
                        @endif
                    @endcan
                    @can('leave.access')
                        @if (auth()->user()->employee)
                            <li>
                                <a href="{{ route('leave.index') }}" @class([
                                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                    'bg-white/10 text-white shadow-sm' => request()->routeIs('leave.index', 'leave.requests.*'),
                                    'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('leave.index', 'leave.requests.*'),
                                ])><x-icon name="calendar" /><span>{{ __('ui.nav.leave') }}</span></a>
                            </li>
                        @endif
                    @endcan
                    @can('overtime.access')
                        @if (auth()->user()->employee)
                            <li>
                                <a href="{{ route('overtime.index') }}" @class([
                                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                    'bg-white/10 text-white shadow-sm' => request()->routeIs('overtime.index', 'overtime.requests.*'),
                                    'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('overtime.index', 'overtime.requests.*'),
                                ])><x-icon name="clock" /><span>{{ __('ui.nav.overtime') }}</span></a>
                            </li>
                        @endif
                    @endcan
                    @if (auth()->user()->can('payroll.view') || auth()->user()->can('salaries.view'))
                        <li>
                            <a href="{{ route('payroll.admin.index') }}" @class([
                                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                'bg-white/10 text-white shadow-sm' => request()->routeIs('payroll.admin.*'),
                                'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('payroll.admin.*'),
                            ])><x-icon name="wallet" /><span>{{ __('ui.nav.payroll') }}</span></a>
                        </li>
                    @endif
                    @foreach ([['chart', 'reports']] as [$icon, $label])
                        <li>
                            <span class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600" aria-disabled="true">
                                <x-icon :name="$icon" />
                                <span class="flex-1">{{ __('ui.nav.'.$label) }}</span>
                                <span class="rounded bg-white/5 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-slate-500">{{ __('ui.nav.soon') }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-7 px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">{{ __('ui.nav.administration') }}</p>
                <ul class="mt-2 space-y-1">
                    @can('viewAny', App\Models\LegalEntity::class)
                        <li>
                            <a href="{{ route('organization.index') }}" @class([
                                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                'bg-white/10 text-white shadow-sm' => request()->routeIs('organization.*'),
                                'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('organization.*'),
                            ])>
                                <x-icon name="grid" />
                                <span>{{ __('ui.nav.organization') }}</span>
                            </a>
                        </li>
                    @endcan
                    <li>
                        <a href="{{ route('security.index') }}" @class([
                            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                            'bg-white/10 text-white shadow-sm' => request()->routeIs('security.*'),
                            'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('security.*'),
                        ])>
                            <x-icon name="lock" />
                            <span>{{ __('ui.nav.security') }}</span>
                        </a>
                    </li>
                    @can('iam.manage')
                        <li>
                            <a href="{{ route('iam.users.index') }}" @class([
                                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                'bg-white/10 text-white shadow-sm' => request()->routeIs('iam.*'),
                                'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('iam.*'),
                            ])>
                                <x-icon name="users" />
                                <span>{{ __('ui.nav.access_management') }}</span>
                            </a>
                        </li>
                    @endcan
                    @if (auth()->user()->can('ess.profile-change.review') && auth()->user()->legalEntityAccess()->where('access_level', 'manage')->effectiveOn(now()->toDateString())->exists())
                        <li>
                            <a href="{{ route('ess.review.index') }}" @class([
                                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                'bg-white/10 text-white shadow-sm' => request()->routeIs('ess.review.*'),
                                'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('ess.review.*'),
                            ])>
                                <x-icon name="inbox" />
                                <span>{{ __('ui.nav.ess_review') }}</span>
                            </a>
                        </li>
                    @endif
                    @if ((auth()->user()->can('attendance.manage') || auth()->user()->can('attendance.view')) && auth()->user()->legalEntityAccess()->effectiveOn(now()->toDateString())->exists())
                        <li><a href="{{ route('attendance.admin.index') }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition', 'bg-white/10 text-white shadow-sm' => request()->routeIs('attendance.admin.*'), 'text-slate-400 hover:bg-white/5 hover:text-white' => !request()->routeIs('attendance.admin.*')])><x-icon name="clock" /><span>{{ __('ui.nav.attendance_admin') }}</span></a></li>
                    @endif
                    @if ((auth()->user()->employee && auth()->user()->can('attendance.corrections.approve-manager')) || (auth()->user()->can('attendance.corrections.review') && auth()->user()->legalEntityAccess()->where('access_level', 'manage')->effectiveOn(now()->toDateString())->exists()))
                        <li><a href="{{ route('attendance.review.queue') }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition', 'bg-white/10 text-white shadow-sm' => request()->routeIs('attendance.review.*'), 'text-slate-400 hover:bg-white/5 hover:text-white' => !request()->routeIs('attendance.review.*')])><x-icon name="inbox" /><span>{{ __('ui.nav.attendance_review') }}</span></a></li>
                    @endif
                    @if ((auth()->user()->can('leave.view') || auth()->user()->can('leave.manage') || auth()->user()->can('leave.report')) && auth()->user()->legalEntityAccess()->effectiveOn(now()->toDateString())->exists())
                        <li><a href="{{ route('leave.admin.index') }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition', 'bg-white/10 text-white shadow-sm' => request()->routeIs('leave.admin.*'), 'text-slate-400 hover:bg-white/5 hover:text-white' => !request()->routeIs('leave.admin.*')])><x-icon name="calendar" /><span>{{ __('ui.nav.leave_admin') }}</span></a></li>
                    @endif
                    @if (auth()->user()->can('leave.approve-manager') || ((auth()->user()->can('leave.review') || auth()->user()->can('leave.confirm-payroll')) && auth()->user()->legalEntityAccess()->where('access_level', 'manage')->effectiveOn(now()->toDateString())->exists()))
                        <li><a href="{{ route('leave.review.index') }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition', 'bg-white/10 text-white shadow-sm' => request()->routeIs('leave.review.*'), 'text-slate-400 hover:bg-white/5 hover:text-white' => !request()->routeIs('leave.review.*')])><x-icon name="inbox" /><span>{{ __('ui.nav.leave_review') }}</span></a></li>
                    @endif
                    @if ((auth()->user()->can('overtime.view') || auth()->user()->can('overtime.manage') || auth()->user()->can('overtime.report')) && auth()->user()->legalEntityAccess()->effectiveOn(now()->toDateString())->exists())
                        <li><a href="{{ route('overtime.admin.index') }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition', 'bg-white/10 text-white shadow-sm' => request()->routeIs('overtime.admin.*'), 'text-slate-400 hover:bg-white/5 hover:text-white' => !request()->routeIs('overtime.admin.*')])><x-icon name="clock" /><span>{{ __('ui.nav.overtime_admin') }}</span></a></li>
                    @endif
                    @if (auth()->user()->can('overtime.approve-manager') || ((auth()->user()->can('overtime.validate') || auth()->user()->can('overtime.include-payroll')) && auth()->user()->legalEntityAccess()->where('access_level', 'manage')->effectiveOn(now()->toDateString())->exists()))
                        <li><a href="{{ route('overtime.review.index') }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition', 'bg-white/10 text-white shadow-sm' => request()->routeIs('overtime.review.*'), 'text-slate-400 hover:bg-white/5 hover:text-white' => !request()->routeIs('overtime.review.*')])><x-icon name="inbox" /><span>{{ __('ui.nav.overtime_review') }}</span></a></li>
                    @endif
                    @can('audit.view')
                        <li>
                            <a href="{{ route('audit.index') }}" @class([
                                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                                'bg-white/10 text-white shadow-sm' => request()->routeIs('audit.*'),
                                'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('audit.*'),
                            ])>
                                <x-icon name="chart" />
                                <span>{{ __('ui.nav.audit') }}</span>
                            </a>
                        </li>
                    @endcan
                </ul>
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="rounded-xl bg-white/5 p-3">
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-300">
                        <span class="size-2 rounded-full bg-emerald-400 shadow-[0_0_0_3px_rgba(52,211,153,.12)]"></span>
                        {{ __('ui.app.environment') }}
                    </div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('ui.footer.privacy') }}</p>
                </div>
            </div>
        </aside>

        <div class="min-h-screen lg:pl-72">
            <header class="surface-panel sticky top-0 z-30 border-b">
                <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        x-on:click="sidebarOpen = true"
                        class="-ml-2 rounded-lg p-2 text-secondary hover:bg-slate-100 hover:text-primary lg:hidden dark:hover:bg-white/10"
                        aria-controls="application-sidebar"
                        :aria-expanded="sidebarOpen"
                        aria-label="{{ __('ui.a11y.open_navigation') }}"
                    >
                        <x-icon name="menu" size="6" />
                    </button>

                    <div class="min-w-0 flex-1">
                        <nav aria-label="{{ __('ui.a11y.breadcrumbs') }}" class="hidden items-center gap-2 text-xs font-semibold text-slate-500 sm:flex dark:text-slate-400">
                            <span>{{ __('ui.topbar.workspace') }}</span>
                            <span aria-hidden="true">/</span>
                            <span class="text-slate-700 dark:text-slate-200" aria-current="page">@yield('page-title', __('ui.nav.overview'))</span>
                        </nav>
                        <p class="truncate text-sm font-bold text-primary sm:hidden">@yield('page-title-mobile', __('ui.nav.overview'))</p>
                    </div>

                    <button type="button" class="hidden min-w-64 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm text-slate-500 transition hover:border-slate-300 md:flex dark:border-slate-700 dark:bg-navy-950 dark:text-slate-400 dark:hover:border-slate-600" aria-label="{{ __('ui.topbar.search') }}" disabled>
                        <x-icon name="search" size="4" />
                        <span class="flex-1 truncate">{{ __('ui.topbar.search') }}</span>
                        <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] dark:border-slate-600 dark:bg-navy-900">{{ __('ui.topbar.search_shortcut') }}</kbd>
                    </button>

                    <form method="POST" action="{{ route('locale.update') }}" class="hidden sm:block">
                        @csrf
                        <label for="locale" class="sr-only">{{ __('ui.topbar.language') }}</label>
                        <select id="locale" name="locale" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm font-bold text-slate-700 dark:border-slate-700 dark:bg-navy-950 dark:text-slate-200">
                            <option value="id" @selected(app()->getLocale() === 'id')>ID</option>
                            <option value="en" @selected(app()->getLocale() === 'en')>EN</option>
                        </select>
                    </form>

                    <button
                        type="button"
                        x-on:click="$store.theme.toggle()"
                        class="rounded-lg border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-950 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                        aria-label="{{ __('ui.theme.toggle') }}"
                    >
                        <span x-show="! $store.theme.dark"><x-icon name="moon" /></span>
                        <span x-show="$store.theme.dark" x-cloak><x-icon name="sun" /></span>
                    </button>

                    @can('notifications.view')
                        <a href="{{ route('notifications.index') }}" class="relative rounded-lg border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-white/10" aria-label="{{ __('ui.a11y.notifications') }}">
                            <x-icon name="bell" />
                            @php($unreadNotificationCount = auth()->user()->unreadNotifications()->count())
                            @if ($unreadNotificationCount > 0)<span class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-brand-600 px-1 text-center text-[10px] font-black leading-5 text-white dark:bg-brand-400 dark:text-navy-950">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>@endif
                        </a>
                    @endcan

                    <div class="hidden items-center gap-3 border-l border-slate-200 pl-3 xl:flex dark:border-slate-700">
                        <span class="grid size-9 place-items-center rounded-lg bg-navy-100 text-xs font-black text-navy-800 dark:bg-brand-500 dark:text-navy-950">PH</span>
                        <span class="leading-tight">
                            <span class="block max-w-40 truncate text-sm font-bold text-primary">{{ auth()->user()->name }}</span>
                            <span class="block max-w-40 truncate text-xs text-secondary">{{ auth()->user()->getRoleNames()->first() ?? __('auth.no_role') }}</span>
                        </span>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-white/10">
                            {{ __('auth.logout') }}
                        </button>
                    </form>
                </div>
            </header>

            <main id="main-content" tabindex="-1" class="px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <div class="mx-auto max-w-[1440px]">
                    @yield('content')
                </div>
            </main>

            <footer class="px-4 pb-8 sm:px-6 lg:px-8">
                <div class="mx-auto flex max-w-[1440px] flex-col gap-2 border-t border-slate-200 pt-5 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:text-slate-400">
                    <p>© {{ now()->year }} {{ __('ui.app.company') }}</p>
                    <p>{{ __('ui.footer.baseline') }}</p>
                </div>
            </footer>
        </div>

        <x-toast-region />
    </body>
</html>
