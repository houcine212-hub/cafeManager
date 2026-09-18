<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Connexion / تسجيل الدخول') · Café Al Nour</title>
    @vite(['resources/css/tokens.css', 'resources/css/base.css'])
    @stack('styles')
</head>
<body>
    <main class="page-shell">
        <div class="cluster" style="justify-content:flex-end; margin-bottom:var(--space-4)">
            <button class="button button--quiet" type="button" data-locale="fr">FR</button>
            <span aria-hidden="true">|</span>
            <button class="button button--quiet" type="button" data-locale="ar">العربية</button>
        </div>

        @if (session('status'))
            <div class="alert alert--success" role="status">{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>

    @vite(['resources/js/core/i18n.js'])
    @stack('scripts')
</body>
</html>
