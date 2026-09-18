<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Menu / القائمة') · Café Al Nour</title>
    @vite(['resources/css/tokens.css', 'resources/css/base.css'])
    @stack('styles')
</head>
<body data-surface="guest">
    <div class="page-shell">
        <header class="split" style="margin-bottom:var(--space-5)">
            <a href="{{ url()->current() }}" aria-label="Café Al Nour">
                <strong>Café Al Nour</strong>
                <span class="muted">Bon café · Bonne ambiance</span>
            </a>
            <div class="cluster">
                <span class="status" data-status="active">@yield('table_context', 'Table / الطاولة')</span>
                <button class="button button--quiet" type="button" data-locale="fr">FR</button>
                <button class="button button--quiet" type="button" data-locale="ar">العربية</button>
            </div>
        </header>

        <div class="alert" role="status" aria-live="polite" data-global-alert hidden></div>
        <main>
            @yield('content')
        </main>
        @yield('cart')
    </div>

    @vite(['resources/js/core/i18n.js', 'resources/js/main-guest.js'])
    @stack('scripts')
</body>
</html>
