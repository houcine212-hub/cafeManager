@extends('layouts.staff')

@section('title', app()->getLocale() === 'ar' ? 'الموظفون' : 'Espace staff')
@section('page_heading', app()->getLocale() === 'ar' ? 'طلبات دخول الزبائن' : 'File d’accès invités')

@push('styles')
    @vite('resources/css/staff.css')
@endpush

@section('navigation')
    <a href="{{ url('/staff') }}"
       data-staff-nav="access"
       aria-current="page"
       class="staff-nav__link">
        <span>Accès invités</span>
        <small>دخول الزبائن</small>
    </a>

    <a href="{{ url('/staff') }}#orders"
       data-staff-nav="orders"
       class="staff-nav__link">
        <span>Commandes</span>
        <small>الطلبات</small>
    </a>

    <a href="{{ url('/staff/service-requests') }}"
       data-staff-nav="service-requests"
       class="staff-nav__link">
        <span>Demandes de service</span>
        <small>طلبات الخدمة</small>
    </a>

    <a href="{{ url('/staff') }}#tables"
       data-staff-nav="tables"
       class="staff-nav__link">
        <span>Tables</span>
        <small>الطاولات</small>
    </a>
@endsection

@section('content')
    <div class="staff-dashboard" data-staff-dashboard>
        <section class="staff-panel" data-staff-view="access" aria-labelledby="pending-access-title">
            <div class="staff-panel__heading">
                <div>
                    <p class="staff-eyebrow">{{ app()->getLocale() === 'ar' ? 'قائمة مباشرة' : 'File en direct' }}</p>
                    <h2 id="pending-access-title">{{ app()->getLocale() === 'ar' ? 'الطلبات المعلقة' : 'Demandes en attente' }}</h2>
                </div>
                <div class="staff-panel__actions">
                    <span class="status" data-status="pending"><span data-access-count>0</span></span>
                    <button class="icon-button" type="button" data-access-refresh title="{{ app()->getLocale() === 'ar' ? 'تحديث' : 'Actualiser' }}">
                        <svg width="17" height="17" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M16 8.5A6.5 6.5 0 0 0 4.3 6M4 3v3.3h3.3"/>
                            <path d="M4 11.5A6.5 6.5 0 0 0 15.7 14M16 17v-3.3h-3.3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="alert" role="status" aria-live="polite" data-access-feedback hidden></div>

            <div class="staff-access-list" data-access-list></div>
        </section>

        <section class="staff-panel staff-panel--muted" data-staff-view="orders" id="orders" hidden>
            <div class="staff-panel__heading">
                <div>
                    <p class="staff-eyebrow">{{ app()->getLocale() === 'ar' ? 'قائمة مباشرة' : 'File en direct' }}</p>
                    <h2>{{ app()->getLocale() === 'ar' ? 'قائمة الطلبات' : 'Commandes en cours' }}</h2>
                </div>
                <button class="icon-button" type="button" data-order-refresh title="{{ app()->getLocale() === 'ar' ? 'تحديث' : 'Actualiser' }}">
                    <svg width="17" height="17" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M16 8.5A6.5 6.5 0 0 0 4.3 6M4 3v3.3h3.3"/>
                        <path d="M4 11.5A6.5 6.5 0 0 0 15.7 14M16 17v-3.3h-3.3"/>
                    </svg>
                </button>
            </div>
            <div class="alert" role="status" aria-live="polite" data-order-feedback hidden></div>
            <div class="staff-order-board" data-order-board></div>
        </section>

        <section class="staff-panel staff-panel--muted" data-staff-view="tables" id="tables" hidden>
            <div class="staff-panel__heading">
                <div>
                    <p class="staff-eyebrow">{{ app()->getLocale() === 'ar' ? 'قريباً' : 'Bientôt' }}</p>
                    <h2>{{ app()->getLocale() === 'ar' ? 'الطاولات' : 'Tables' }}</h2>
                </div>
            </div>
            <p class="muted">{{ app()->getLocale() === 'ar' ? 'هاد الصفحة مازال كنبنيوها.' : 'Cette page est en cours de construction.' }}</p>
        </section>
    </div>
@endsection
