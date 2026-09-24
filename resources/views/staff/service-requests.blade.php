@extends('layouts.staff')

@section('title', 'Demandes de service / طلبات الخدمة')
@section('page_heading', 'Demandes de service / طلبات الخدمة')

@push('styles')
    @vite('resources/css/staff.css')
@endpush

@section('content')
    <div class="staff-page" data-staff-service-requests>
        <section class="staff-page__intro">
            <div>
                <p class="staff-eyebrow">Service en direct / الخدمة المباشرة</p>
                <h2>Demandes de service</h2>
                <p class="muted">Répondez aux appels et aux demandes d’addition des tables.</p>
            </div>
            <button class="button button--primary" type="button" data-service-request-refresh>
                Actualiser / تحديث
            </button>
        </section>

        <div class="alert" role="status" aria-live="polite" data-service-request-feedback hidden></div>

        <section class="staff-panel staff-panel--queue" aria-labelledby="service-requests-title">
            <div class="staff-panel__heading">
                <div>
                    <p class="staff-eyebrow">File active / القائمة الحالية</p>
                    <h2 id="service-requests-title">À traiter / قيد المعالجة</h2>
                </div>
                <span class="status" data-status="active">LIVE</span>
            </div>

            <div class="staff-service-request-list" data-service-request-list>
                <p class="staff-empty">Chargement / جار التحميل</p>
            </div>
        </section>
    </div>
@endsection
