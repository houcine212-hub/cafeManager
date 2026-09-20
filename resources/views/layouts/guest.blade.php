<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Menu / القائمة')</title>
    @vite(['resources/css/tokens.css', 'resources/css/base.css'])
    @stack('styles')
</head>
<body data-surface="guest">
    <div class="guest-document">
        <div class="alert" role="status" aria-live="polite" data-global-alert hidden></div>
        @yield('content')
    </div>

    @vite(['resources/js/core/i18n.js', 'resources/js/main-guest.js'])
    @stack('scripts')
</body>
</html>
