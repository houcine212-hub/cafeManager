@extends('layouts.staff')

@section('title', 'File des commandes / قائمة الطلبات')
@section('page_heading', 'File des commandes / قائمة الطلبات')

@push('styles')
    @vite('resources/css/staff.css')
@endpush

@section('content')
    <div class="staff-page" data-staff-order-queue>
        <section class="staff-page__intro">
            <div>
                <p class="staff-eyebrow">Service en direct / الخدمة المباشرة</p>
                <h2>File des commandes</h2>
                <p class="muted">Traitez les commandes reçues par QR et accompagnez-les jusqu’au service.</p>
            </div>
            <button class="button button--primary" type="button" data-order-refresh>Actualiser / تحديث</button>
        </section>

        <div class="alert" role="status" aria-live="polite" data-order-feedback hidden></div>

        <section class="staff-panel staff-panel--queue" aria-labelledby="orders-title">
            <div class="staff-panel__heading">
                <div>
                    <p class="staff-eyebrow">Live queue / قائمة مباشرة</p>
                    <h2 id="orders-title">Commandes à traiter / الطلبات قيد المعالجة</h2>
                </div>
                <span class="status" data-status="active">LIVE</span>
            </div>
            <div class="staff-order-list" data-order-list>
                <p class="staff-empty">Chargement des commandes / جار تحميل الطلبات</p>
            </div>
        </section>
    </div>
@endsection
