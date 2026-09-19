@extends('layouts.staff')

@section('title', 'Staff / الموظفون')
@section('page_heading', 'Staff Front / واجهة الموظفين')
@section('role_label', ucfirst($role) . ' / الموظفون')
@section('cafe_context', 'Café / المقهى')

@push('styles')
    @vite('resources/css/staff.css')
@endpush

@section('navigation')
    <a href="{{ url('/staff') }}" aria-current="page">Guest access / دخول الزبائن</a>
    <a href="{{ url('/staff') }}#orders">Orders / الطلبات</a>
    <a href="{{ url('/staff') }}#tables">Tables / الطاولات</a>
@endsection

@section('content')
    <div class="staff-dashboard" data-staff-dashboard>
        <section class="staff-hero">
            <div>
                <p class="staff-eyebrow">Table service / خدمة الطاولات</p>
                <h2>Guest access queue / طلبات دخول الزبائن</h2>
                <p class="muted">راجع الطلبات ووافق عليها قبل السماح بالطلب.</p>
            </div>
            <button class="button button--quiet" type="button" data-access-refresh>Refresh / تحديث</button>
        </section>

        <div class="alert" role="status" aria-live="polite" data-access-feedback hidden></div>

        <section class="staff-panel" aria-labelledby="pending-access-title">
            <div class="staff-panel__heading">
                <div>
                    <p class="staff-eyebrow">Live queue / قائمة مباشرة</p>
                    <h2 id="pending-access-title">Pending requests / الطلبات المعلقة</h2>
                </div>
                <span class="status" data-status="pending"><span data-access-count>0</span></span>
            </div>
            <div class="staff-access-list" data-access-list>
                <p class="staff-empty">Loading requests / جار تحميل الطلبات</p>
            </div>
        </section>

        <section class="staff-panel staff-panel--muted" id="orders">
            <div class="staff-panel__heading">
                <div>
                    <p class="staff-eyebrow">Next surface / المرحلة التالية</p>
                    <h2>Order queue / قائمة الطلبات</h2>
                </div>
                <span class="muted">Coming next / قريباً</span>
            </div>
            <p class="muted">The live order queue will use the existing staff order endpoint in the next Phase 4 slice.</p>
        </section>
    </div>
@endsection
