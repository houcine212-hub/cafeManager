<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', app()->getLocale() === 'ar' ? 'الموظفون' : 'Espace staff')</title>
    @vite(['resources/css/tokens.css', 'resources/css/base.css'])
    @stack('styles')
</head>
<body data-surface="staff">
    <div class="staff-shell">
        <aside class="staff-sidebar" aria-label="{{ app()->getLocale() === 'ar' ? 'تنقل الموظفين' : 'Navigation du personnel' }}">
            <div class="staff-brand">
                <span class="staff-brand__mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 10h13a3 3 0 0 1 0 6h-1M4 10v7a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-1M4 10V7a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v3"/>
                    </svg>
                </span>
                <span class="staff-brand__name">{{ config('app.name', app()->getLocale() === 'ar' ? 'واجهة الموظفين' : 'Espace staff') }}</span>
            </div>

            <nav class="staff-nav" aria-label="Navigation">
                @yield('navigation')
            </nav>

            <div class="staff-sidebar__footer">
                <span class="status-pill" data-connection-indicator>
                    <svg viewBox="0 0 8 8" class="status-dot" aria-hidden="true"><circle cx="4" cy="4" r="4"/></svg>
                    <span data-connection-label>{{ app()->getLocale() === 'ar' ? 'جارٍ التحقق' : 'Vérification…' }}</span>
                </span>

                <div class="staff-account">
                    <span class="staff-account__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($role ?? 'S', 0, 1)) }}</span>
                    <span class="staff-account__role">{{ ucfirst($role ?? ($request->user()->role?->name ?? 'staff')) }}</span>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="staff-logout-form">
                    @csrf
                    <button class="icon-button icon-button--ghost" type="submit" title="{{ app()->getLocale() === 'ar' ? 'خروج' : 'Déconnexion' }}">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 17H4.8A1.8 1.8 0 0 1 3 15.2V4.8A1.8 1.8 0 0 1 4.8 3H8"/>
                            <path d="M13 14l4-4-4-4M17 10H7.5"/>
                        </svg>
                    </button>
                </form>
            </div>
        </aside>

        <main class="staff-main">
            <header class="staff-topbar">
                <div>
                    <h1>@yield('page_heading', app()->getLocale() === 'ar' ? 'الموظفون' : 'Espace staff')</h1>
                </div>
                <div class="staff-topbar__tools">
                    <span class="staff-clock" data-clock aria-hidden="true"></span>

                    <label class="lang-select">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                            <circle cx="10" cy="10" r="7.5"/>
                            <path d="M2.5 10h15M10 2.5c2 2.2 3 4.8 3 7.5s-1 5.3-3 7.5c-2-2.2-3-4.8-3-7.5s1-5.3 3-7.5z"/>
                        </svg>
                        <select data-locale-select aria-label="{{ app()->getLocale() === 'ar' ? 'اللغة' : 'Langue' }}">
                            <option value="fr">Français</option>
                            <option value="ar">العربية</option>
                        </select>
                        <svg class="lang-select__chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 8l5 5 5-5"/>
                        </svg>
                    </label>

                    @yield('header_actions')
                </div>
            </header>

            <div class="alert" role="status" aria-live="polite" data-global-alert hidden></div>
            @yield('content')
        </main>
    </div>

    @vite(['resources/js/core/i18n.js', 'resources/js/main-staff.js'])
    @stack('scripts')
</body>
</html>
