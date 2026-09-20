@extends('layouts.staff')

@section('title', 'Encaissement / الأداء')
@section('page_heading', 'Encaissement / الأداء')

@push('styles')
    @vite('resources/css/staff.css')
@endpush

@section('content')
    <div class="staff-page" data-staff-payments>
        <section class="staff-page__intro">
            <div>
                <p class="staff-eyebrow">Clôture de session / إغلاق الجلسة</p>
                <h2>Encaissement</h2>
                <p class="muted">Vérifiez l’addition et enregistrez le paiement avant de libérer la table.</p>
            </div>
            <button class="button button--quiet" type="button" data-payments-refresh>Actualiser / تحديث</button>
        </section>

        @forelse ($sessions as $session)
            <article class="staff-payment-card">
                <div class="staff-payment-card__icon" aria-hidden="true">▥</div>
                <div>
                    <p class="staff-eyebrow">Paiement à vérifier / خاص التحقق من الأداء</p>
                    <h2>{{ $session->table?->label ?? 'Table' }}</h2>
                    <p class="muted">Session #{{ $session->id }} · La session est prête pour l’encaissement.</p>
                </div>
                <button class="button button--primary" type="button" disabled>Vérifier la session / التحقق من الجلسة</button>
            </article>
        @empty
            <section class="staff-empty-state staff-empty-state--compact">
                <span class="staff-empty-state__art" aria-hidden="true">▥</span>
                <h2>Aucun paiement à vérifier</h2>
                <p>Les sessions qui arrivent à l’encaissement apparaîtront ici.</p>
            </section>
        @endforelse
    </div>
@endsection
