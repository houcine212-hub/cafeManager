@extends('layouts.staff')

@section('title', 'Détails de la table / تفاصيل الطاولة')
@section('page_heading', 'Détails de la table / تفاصيل الطاولة')

@push('styles')
    @vite('resources/css/staff.css')
@endpush

@section('content')
    <div class="staff-page" data-staff-tables>
        <section class="staff-page__intro">
            <div>
                <p class="staff-eyebrow">Salle et terrasses / القاعة والتراسات</p>
                <h2>Gérez vos tables</h2>
                <p class="muted">Ouvrez une session, suivez les commandes et accueillez les clients QR.</p>
            </div>
            <button class="button button--quiet" type="button" data-tables-refresh>Actualiser / تحديث</button>
        </section>

        <section class="staff-panel" id="access" data-staff-access-queue aria-labelledby="access-title">
            <div class="staff-panel__heading">
                <div>
                    <p class="staff-eyebrow">Accès client / دخول الزبون</p>
                    <h2 id="access-title">Demandes en attente / الطلبات المعلقة</h2>
                </div>
                <div class="cluster">
                    <span class="status" data-status="pending"><span data-access-count>0</span></span>
                    <button class="button button--quiet" type="button" data-access-refresh>Actualiser</button>
                </div>
            </div>
            <div class="alert" role="status" aria-live="polite" data-access-feedback hidden></div>
            <div class="staff-access-list" data-access-list>
                <p class="staff-empty">Chargement des demandes / جار تحميل الطلبات</p>
            </div>
        </section>

        <section class="staff-table-grid" aria-label="Tables / الطاولات">
            @foreach ($tables as $table)
                @php($session = $sessions->get($table->id))
                <article class="staff-table-card" data-table-id="{{ $table->id }}">
                    <div class="staff-table-card__topline">
                        <span class="staff-table-card__icon" aria-hidden="true">▤</span>
                        <span class="status {{ $session ? '' : 'status--closed' }}" data-status="{{ $session ? 'active' : 'closed' }}">
                            {{ $session ? 'Occupée' : 'Disponible' }}
                        </span>
                    </div>
                    <h2>{{ $table->label }}</h2>
                    <p class="muted">Capacité : {{ $table->capacity }} personnes</p>
                    @if ($session)
                        <div class="staff-table-card__meta">
                            <span>{{ $session->active_orders_count }} commande(s) active(s)</span>
                            <span>Session #{{ $session->id }}</span>
                        </div>
                        <button class="button button--quiet" type="button" disabled>Gérer la table / تسيير الطاولة</button>
                    @else
                        <button class="button button--primary" type="button" data-open-table="{{ $table->id }}">Ouvrir la table / فتح الطاولة</button>
                    @endif
                </article>
            @endforeach
        </section>
    </div>
@endsection
