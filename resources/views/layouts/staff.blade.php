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
            <a href="{{ route('staff.dashboard') }}" class="brand-link">
                <span class="brand-mark" aria-hidden="true">✦</span>

                <span>
                    <strong>Café Al Nour</strong>
                    <small>Bon café · Bonne ambiance</small>
                </span>
            </a>

            <nav class="staff-nav" aria-label="Navigation">
                <a
                    class="{{ request()->routeIs('staff.dashboard') ? 'is-active' : '' }}"
                    href="{{ route('staff.dashboard') }}"
                >
                    <span class="nav-icon" aria-hidden="true">⌂</span>

                    <span>
                        Tableau de bord
                        <small>لوحة التحكم</small>
                    </span>
                </a>

                <a
                    class="{{ request()->routeIs('staff.orders') ? 'is-active' : '' }}"
                    href="{{ route('staff.orders') }}"
                >
                    <span class="nav-icon" aria-hidden="true">▣</span>

                    <span>
                        File des commandes
                        <small>قائمة الطلبات</small>
                    </span>
                </a>

                <a
                    class="{{ request()->routeIs('staff.service-requests') ? 'is-active' : '' }}"
                    href="{{ route('staff.service-requests') }}"
                >
                    <span class="nav-icon" aria-hidden="true">♢</span>

                    <span>
                        Demandes de service
                        <small>طلبات الخدمة</small>
                    </span>
                </a>

                <a
                    class="{{ request()->routeIs('staff.tables') ? 'is-active' : '' }}"
                    href="{{ route('staff.tables') }}"
                >
                    <span class="nav-icon" aria-hidden="true">▤</span>

                    <span>
                        Détails de la table
                        <small>تفاصيل الطاولة</small>
                    </span>
                </a>

                <a
                    class="{{ request()->routeIs('staff.payments') ? 'is-active' : '' }}"
                    href="{{ route('staff.payments') }}"
                >
                    <span class="nav-icon" aria-hidden="true">▥</span>

                    <span>
                        Encaissement
                        <small>الأداء</small>
                    </span>
                </a>
            </nav>

            <div class="staff-sidebar__footer">
                <span class="staff-open-state">
                    <i></i>
                    Ouvert
                    <small>مفتوح</small>
                </span>

                <span class="staff-clock">
                    {{ now()->format('H:i') }}
                    <small>{{ now()->format('D d M') }}</small>
                </span>

                <span
                    class="connection-indicator"
                    data-connection-indicator
                >
                    Checking connection / جار التحقق
                </span>

                <span class="staff-role">
                    {{ ucfirst($role ?? 'staff') }}
                    <small>الموظفون</small>
                </span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button class="button button--quiet" type="submit">
                        Logout / خروج
                    </button>
                </form>
            </div>
        </aside>

        <main class="staff-main">
            <header class="staff-topbar">
                <div>
                    <p class="staff-breadcrumb">Café Al Nour</p>
                    <h1>@yield('page_heading', 'Staff / الموظفون')</h1>
                </div>

                <div class="staff-topbar__actions">
                    <button class="button button--quiet" type="button" data-locale="fr">
                        FR
                    </button>

                    <button class="button button--quiet" type="button" data-locale="ar">
                        العربية
                    </button>

                    <span class="staff-user" aria-label="Current user">
                        <b>◉</b>

                        <span>
                            {{ ucfirst($role ?? 'staff') }}
                            <small>Serveur</small>
                        </span>
                    </span>

                    @yield('header_actions')
                </div>
            </header>

            <div
                class="alert"
                role="status"
                aria-live="polite"
                data-global-alert
                hidden
            ></div>

            @yield('content')
        </main>
    </div>

    @vite(['resources/js/core/i18n.js', 'resources/js/main-staff.js'])
    @stack('scripts')
</body>
</html>