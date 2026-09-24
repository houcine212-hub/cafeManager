@extends('layouts.guest')

@section('title', app()->getLocale() === 'ar' ? 'القائمة' : 'Menu')
@section('table_context', ($table ?? '') )

@push('styles')
    @vite('resources/css/guest.css')
@endpush

@section('content')
    <div class="guest-app" data-guest-menu data-table="{{ $table }}">
        <header class="guest-topbar">
            <button class="guest-icon-button" type="button" data-toggle-menu aria-label="{{ app()->getLocale() === 'ar' ? 'فتح القائمة' : 'Ouvrir le menu' }}">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M3 6h14M3 10h14M3 14h14"/></svg>
            </button>
            <a class="guest-brand" href="{{ url()->current() }}" aria-label="{{ app()->getLocale() === 'ar' ? 'القائمة' : 'Menu' }}">
                <span>
                    <strong>{{ app()->getLocale() === 'ar' ? 'القائمة' : 'Menu' }}</strong>
                    <small>{{ app()->getLocale() === 'ar' ? 'خدمة الطاولة' : 'Service à table' }}</small>
                </span>
            </a>
            <div class="guest-topbar__tools">
                <span class="guest-table-chip">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="8" width="14" height="2.2" rx="0.9"/>
                        <path d="M5.2 10.2V16M14.8 10.2V16"/>
                    </svg>
                    <span>{{ $table }}</span>
                </span>
            </div>
        </header>

        <main class="guest-views">
            <section class="guest-view is-active" data-guest-view="menu" aria-labelledby="menu-title">
                <header class="guest-view__intro">
                    <div>
                        <p class="guest-eyebrow">{{ $table }}</p>
                        <h1 id="menu-title">{{ app()->getLocale() === 'ar' ? 'القائمة' : 'Menu' }}</h1>
                        <p class="muted">{{ app()->getLocale() === 'ar' ? 'اختر المنتجات ديالك' : 'Choisissez vos produits' }}</p>
                    </div>
                    <button class="guest-approval-link" type="button" data-open-view="approval">
                        {{ app()->getLocale() === 'ar' ? 'الدخول' : 'Accès' }}
                    </button>
                </header>

                <div class="alert" role="status" aria-live="polite" data-menu-status hidden></div>

                <nav class="guest-categories" aria-label="{{ app()->getLocale() === 'ar' ? 'الفئات' : 'Catégories' }}" data-category-list></nav>

                <section class="guest-products-section" aria-labelledby="products-title">
                    <div class="guest-section-heading">
                        <h2 id="products-title">{{ app()->getLocale() === 'ar' ? 'المنتجات' : 'Produits' }}</h2>
                        <button class="guest-text-button" type="button" data-menu-retry>{{ app()->getLocale() === 'ar' ? 'تحديث' : 'Actualiser' }}</button>
                    </div>
                    <div class="guest-product-grid" data-product-grid aria-live="polite"></div>
                </section>
            <section class="guest-service" aria-labelledby="service-title">
                <p class="guest-service__eyebrow">Service à table / الخدمة للطاولة</p>
                <h2 id="service-title">Besoin de nous ? / واش محتاجينا؟</h2>
                <p class="muted">Ces actions deviennent disponibles après l’approbation de l’accès.</p>
                <div class="guest-service__actions">
                    <button class="button button--primary" type="button" data-service-request="waiter" disabled>
                        Appeler le serveur / عيط للسيرفر
                    </button>
                    <button class="button" type="button" data-service-request="bill" disabled>
                        Demander l’addition / طلب الحساب
                    </button>
                </div>
                <div class="alert" role="status" aria-live="polite" data-service-request-result hidden></div>
            </section>

            </section>

            <section class="guest-view" data-guest-view="cart" aria-labelledby="cart-title" hidden>
                <header class="guest-screen-heading">
                    <button class="guest-back-button" type="button" data-open-view="menu" aria-label="{{ app()->getLocale() === 'ar' ? 'رجوع' : 'Retour' }}">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4l-6 6 6 6"/></svg>
                    </button>
                    <div>
                        <p class="guest-eyebrow">{{ $table }}</p>
                        <h1 id="cart-title">{{ app()->getLocale() === 'ar' ? 'السلة' : 'Panier' }}</h1>
                    </div>
                    <span class="guest-count-badge" data-cart-count>0</span>
                </header>

                <div class="alert" role="status" aria-live="polite" data-cart-notice hidden></div>

                <div class="guest-cart-lines" data-cart-lines></div>

                <div class="guest-note-card" data-cart-note-card>
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 16l.6-3L13 4.6a1.4 1.4 0 0 1 2 0l.4.4a1.4 1.4 0 0 1 0 2L7 15.4z"/></svg>
                    <span>{{ app()->getLocale() === 'ar' ? 'ملاحظة اختيارية' : 'Note optionnelle' }}</span>
                    <small>{{ app()->getLocale() === 'ar' ? 'زيد ملاحظتك فكل منتج' : 'Ajoutez vos préférences dans chaque article' }}</small>
                </div>

                <section class="guest-totals" data-cart-totals aria-label="{{ app()->getLocale() === 'ar' ? 'الملخص' : 'Résumé' }}">
                    <div><span>{{ app()->getLocale() === 'ar' ? 'المجموع الفرعي' : 'Sous-total' }}</span><strong data-cart-total>0.00 DH</strong></div>
                    <div><span>{{ app()->getLocale() === 'ar' ? 'رسوم الخدمة' : 'Frais de service' }}</span><strong>0.00 DH</strong></div>
                    <div class="guest-totals__grand"><span>{{ app()->getLocale() === 'ar' ? 'المجموع التقريبي' : 'Total estimé' }}</span><strong data-cart-total>0.00 DH</strong></div>
                </section>

                <div class="alert" role="status" aria-live="polite" data-order-result hidden></div>
                <button class="guest-primary-cta" type="button" data-submit-order disabled>
                    <span>{{ app()->getLocale() === 'ar' ? 'تأكيد الطلب' : 'Confirmer la commande' }}</span>
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4l6 6-6 6"/></svg>
                </button>
            </section>

            <section class="guest-view" data-guest-view="approval" aria-labelledby="approval-title" hidden>
                <header class="guest-screen-heading">
                    <button class="guest-back-button" type="button" data-open-view="menu" aria-label="{{ app()->getLocale() === 'ar' ? 'رجوع' : 'Retour' }}">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4l-6 6 6 6"/></svg>
                    </button>
                    <div>
                        <p class="guest-eyebrow">{{ $table }}</p>
                        <h1 id="approval-title">{{ app()->getLocale() === 'ar' ? 'الموافقة' : 'Approbation' }}</h1>
                    </div>
                </header>

                <div class="guest-approval-art" data-approval-art aria-hidden="true">
                    <span data-approval-icon></span>
                </div>
                <div class="guest-approval-copy">
                    <h2 data-approval-heading>{{ app()->getLocale() === 'ar' ? 'في انتظار موافقة الموظفين' : 'En attente de l’approbation du personnel' }}</h2>
                    <p data-approval-sub></p>
                </div>

                <div class="guest-progress" data-approval-progress aria-label="{{ app()->getLocale() === 'ar' ? 'تقدم الطلب' : 'Progression de la commande' }}">
                    <div class="guest-progress__step is-done">
                        <span><svg width="10" height="10" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg></span>
                        <small>{{ app()->getLocale() === 'ar' ? 'تم إرسال الطلب' : 'Commande envoyée' }}</small>
                    </div>
                    <div class="guest-progress__line"></div>
                    <div class="guest-progress__step is-current" data-approval-step>
                        <span></span>
                        <small>{{ app()->getLocale() === 'ar' ? 'في الانتظار' : 'En attente' }}</small>
                    </div>
                    <div class="guest-progress__line"></div>
                    <div class="guest-progress__step"><span></span><small>{{ app()->getLocale() === 'ar' ? 'قيد التحضير' : 'Préparation' }}</small></div>
                    <div class="guest-progress__line"></div>
                    <div class="guest-progress__step"><span></span><small>{{ app()->getLocale() === 'ar' ? 'جاهزة' : 'Prête' }}</small></div>
                </div>

                <div class="guest-access-card">
                    <p class="guest-access__status" data-access-status aria-live="polite"></p>
                    <button class="guest-primary-cta" type="button" data-request-access>
                        <span data-request-access-label>{{ app()->getLocale() === 'ar' ? 'طلب الدخول' : 'Demander l’accès' }}</span>
                    </button>
                </div>

                <p class="guest-info-note" data-approval-note>{{ app()->getLocale() === 'ar' ? 'سيتم تفعيل طلبك بعد موافقة الموظفين.' : 'Votre commande sera activée après l’approbation du personnel.' }}</p>
            </section>

            <section class="guest-view" data-guest-view="orders" aria-labelledby="orders-title" hidden>
                <header class="guest-screen-heading">
                    <button class="guest-back-button" type="button" data-open-view="menu" aria-label="{{ app()->getLocale() === 'ar' ? 'رجوع' : 'Retour' }}">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4l-6 6 6 6"/></svg>
                    </button>
                    <div>
                        <p class="guest-eyebrow">{{ $table }}</p>
                        <h1 id="orders-title">{{ app()->getLocale() === 'ar' ? 'طلباتي' : 'Mes commandes' }}</h1>
                    </div>
                </header>
                <div class="alert" role="status" aria-live="polite" data-orders-status hidden></div>
                <div class="guest-orders-list" data-orders-list></div>
                <button class="guest-text-button guest-orders-retry" type="button" data-orders-retry>{{ app()->getLocale() === 'ar' ? 'تحديث' : 'Actualiser' }}</button>
            </section>
        </main>

        <nav class="guest-bottom-nav" aria-label="{{ app()->getLocale() === 'ar' ? 'تنقل الزبون' : 'Navigation client' }}">
            <button class="is-active" type="button" data-nav-view="menu">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3v6M4 3v3a2 2 0 0 0 4 0V3M14.5 3c-1.4 0-2.5 1.6-2.5 4s1.1 4 2.5 4V17"/></svg>
                <small>{{ app()->getLocale() === 'ar' ? 'القائمة' : 'Menu' }}</small>
            </button>
            <button type="button" data-nav-view="cart">
                <span class="guest-nav-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3h1.6L6 12.5h9l1.5-6.5H5"/><circle cx="8" cy="16.5" r="1.2"/><circle cx="14" cy="16.5" r="1.2"/></svg>
                    <b data-nav-cart-count>0</b>
                </span>
                <small>{{ app()->getLocale() === 'ar' ? 'السلة' : 'Panier' }}</small>
            </button>
            <button type="button" data-nav-view="orders">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 2.5h10v15l-2-1.3-2 1.3-2-1.3-2 1.3-2-1.3z"/><path d="M7.3 6.5h5.4M7.3 9.5h5.4M7.3 12.5h3.4"/></svg>
                <small>{{ app()->getLocale() === 'ar' ? 'طلباتي' : 'Commandes' }}</small>
            </button>
        </nav>
    </div>
@endsection
