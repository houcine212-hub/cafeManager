<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Staff / الموظفون') · Café Al Nour</title>
    @vite(['resources/css/tokens.css', 'resources/css/base.css'])
    @stack('styles')
</head>
<body data-surface="staff">
    <div class="staff-shell">
        <aside class="staff-sidebar" aria-label="Staff navigation / تنقل الموظفين">
            <a href="{{ url('/') }}" class="brand-link"><strong>Café Al Nour</strong><span>Bon café · Bonne ambiance</span></a>
            <nav class="stack" aria-label="Navigation">
                @yield('navigation')
            </nav>
            <div class="stack" style="margin-top:auto">
                <span class="connection-indicator" data-connection-indicator>Checking connection / جار التحقق</span>
                <span class="muted">@yield('role_label', 'Staff / الموظفون')</span>
            </div>
        </aside>

        <main class="staff-main">
            <header class="split" style="margin-bottom:var(--space-6)">
                <div>
                    <p class="muted">@yield('cafe_context', 'Café Al Nour')</p>
                    <h1>@yield('page_heading', 'Staff / الموظفون')</h1>
                </div>
                <div class="cluster">
                    <button class="button button--quiet" type="button" data-locale="fr">FR</button>
                    <button class="button button--quiet" type="button" data-locale="ar">العربية</button>
                    @yield('header_actions')
                </div>
            </header>

            <div class="alert" role="status" aria-live="polite" data-global-alert hidden></div>
            @yield('content')
        </main>
    </div>

    @vite(['resources/js/core/i18n.js', 'resources/js/core/connectivity.js', 'resources/js/main-staff.js'])
    @stack('scripts')
</body>
</html>
