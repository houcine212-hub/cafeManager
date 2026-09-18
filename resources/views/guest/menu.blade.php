@extends('layouts.guest')

@section('title', 'Menu / القائمة')
@section('table_context', 'Table ' . $table . ' / الطاولة')

@push('styles')
    @vite('resources/css/guest.css')
@endpush

@section('content')
    <div class="guest-page" data-guest-menu>
        <header class="guest-page__intro">
            <p class="muted">{{ $table }}</p>

            <h1>Menu / القائمة</h1>

            <p class="muted">
                Choisissez vos produits avant de demander l’accès /
                اختار المنتجات قبل طلب الدخول
            </p>
        </header>

        <section class="guest-access surface" aria-labelledby="guest-access-title">
            <div class="split">
                <div>
                    <h2 id="guest-access-title">
                        Accès à la table / الدخول إلى الطاولة
                    </h2>

                    <p class="guest-access__status"
                       data-access-status
                       aria-live="polite">
                        Vérification de l’accès /
                        جار التحقق من الدخول
                    </p>
                </div>

                <button class="button button--primary"
                        type="button"
                        data-request-access>
                    Demander l’accès / طلب الدخول
                </button>
            </div>
        </section>

        <div class="alert"
             role="status"
             aria-live="polite"
             data-menu-status
             hidden></div>

        <nav class="guest-categories"
              aria-label="Catégories / الفئات"
              data-category-list></nav>

        <section aria-labelledby="products-title">
            <div class="split" style="margin-bottom:var(--space-3)">
                <h2 id="products-title">
                    Produits / المنتجات
                </h2>

                <button class="button button--quiet"
                        type="button"
                        data-menu-retry>
                    Actualiser / تحديث
                </button>
            </div>

            <div class="guest-product-grid"
                 data-product-grid
                 aria-live="polite">
                <p class="alert">
                    Chargement du menu / جار تحميل القائمة
                </p>
            </div>
        </section>

        <div class="alert"
             role="status"
             aria-live="polite"
             data-order-result
             hidden></div>

        <section class="guest-cart"
                 aria-labelledby="cart-title">
            <div class="guest-cart__summary">
                <h2 id="cart-title">
                    Panier / السلة
                </h2>

                <span>
                    <span data-cart-count>0</span>
                    articles ·
                    <strong data-cart-total>0.00 DH</strong>
                </span>
            </div>

            <div data-cart-lines>
                <p>Votre panier est vide / السلة فارغة</p>
            </div>

            <p class="muted">
                Sous-total estimé / المجموع التقديري،
                يحسبه backend من جديد
            </p>

            <button class="button guest-cart__submit"
                    type="button"
                    data-submit-order
                    disabled>
                Confirmer la commande / تأكيد الطلب
            </button>
        </section>
    </div>
@endsection
