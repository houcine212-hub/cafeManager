@extends('layouts.staff')

@section('title', 'Menu et stock / القائمة والمخزون')
@section('page_heading', 'Menu et stock / القائمة والمخزون')

@push('styles')
    @vite('resources/css/staff.css')
@endpush

@section('content')
    <div class="staff-page">
        <section class="staff-page__intro">
            <div>
                <p class="staff-eyebrow">Catalogue du café / كاتالوغ المقهى</p>
                <h2>Gérez le menu et le stock</h2>
                <p class="muted">
                    Modifiez les prix, la disponibilité et les quantités sans supprimer l’historique des commandes.
                </p>
            </div>
        </section>

        @if (session('status'))
            <div class="alert alert--success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert--error" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 22rem), 1fr)); gap:var(--space-5); align-items:start">
            <section class="staff-panel">
                <div class="staff-panel__heading">
                    <div>
                        <p class="staff-eyebrow">Organisation / التنظيم</p>
                        <h2>Catégories</h2>
                    </div>
                </div>

                <form class="stack" method="POST" action="{{ route('staff.menu.categories.store') }}">
                    @csrf

                    <div class="field">
                        <label for="category_name">Nom / الاسم</label>
                        <input id="category_name" name="name" type="text" value="{{ old('name') }}" required>
                    </div>

                    <div class="field">
                        <label for="category_sort_order">Ordre / الترتيب</label>
                        <input id="category_sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}">
                    </div>

                    <button class="button button--primary" type="submit">
                        Ajouter / إضافة
                    </button>
                </form>

                <div class="stack" style="margin-top:var(--space-5)">
                    @forelse ($categories as $category)
                        <form class="alert stack" method="POST" action="{{ route('staff.menu.categories.update', $category->id) }}">
                            @csrf
                            @method('PATCH')

                            <div class="field">
                                <label for="category_{{ $category->id }}_name">Nom</label>
                                <input id="category_{{ $category->id }}_name" name="name" type="text" value="{{ $category->name }}" required>
                            </div>

                            <div class="cluster">
                                <input name="sort_order" type="number" min="0" value="{{ $category->sort_order }}" aria-label="Ordre">
                                <label class="cluster">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($category->is_active)>
                                    <span>Active</span>
                                </label>
                                <button class="button button--quiet" type="submit">Enregistrer</button>
                            </div>
                        </form>
                    @empty
                        <p class="muted">Aucune catégorie.</p>
                    @endforelse
                </div>
            </section>

            <section class="staff-panel">
                <div class="staff-panel__heading">
                    <div>
                        <p class="staff-eyebrow">Nouveau produit / منتج جديد</p>
                        <h2>Ajouter au menu</h2>
                    </div>
                </div>

                <form class="stack" method="POST" action="{{ route('staff.menu.products.store') }}">
                    @csrf

                    <div class="field">
                        <label for="product_category_id">Catégorie / الفئة</label>
                        <select id="product_category_id" name="category_id" required>
                            <option value="">Choisir / اختار</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="product_name">Nom / الاسم</label>
                        <input id="product_name" name="name" type="text" required>
                    </div>

                    <div class="field">
                        <label for="product_price">Prix MAD / الثمن</label>
                        <input id="product_price" name="price" type="number" min="0" step="0.01" required>
                    </div>

                    <div class="field">
                        <label for="product_description">Description / الوصف</label>
                        <textarea id="product_description" name="description" rows="3"></textarea>
                    </div>

                    <label class="cluster">
                        <input type="hidden" name="track_stock" value="0">
                        <input type="checkbox" name="track_stock" value="1">
                        <span>Suivre le stock / تتبع المخزون</span>
                    </label>

                    <div class="field">
                        <label for="product_stock_quantity">Stock initial / المخزون الأولي</label>
                        <input id="product_stock_quantity" name="stock_quantity" type="number" min="0" value="0">
                    </div>

                    <button class="button button--primary" type="submit" @disabled($categories->isEmpty())>
                        Ajouter le produit / زيد المنتج
                    </button>
                </form>
            </section>
        </div>

        <section class="staff-panel" style="margin-top:var(--space-5)">
            <div class="staff-panel__heading">
                <div>
                    <p class="staff-eyebrow">Catalogue actif / الكاتالوغ الحالي</p>
                    <h2>Produits ({{ $products->count() }})</h2>
                </div>
            </div>

            <div class="stack">
                @forelse ($products as $product)
                    <form class="staff-order-card" method="POST" action="{{ route('staff.menu.products.update', $product->id) }}">
                        @csrf
                        @method('PATCH')

                        <div class="staff-order-card__header">
                            <div>
                                <strong>{{ $product->name }}</strong>
                                <p class="muted">{{ $product->category?->name }}</p>
                            </div>

                            <span class="status" data-status="{{ $product->is_active && $product->is_available ? 'active' : 'closed' }}">
                                {{ $product->is_active && $product->is_available ? 'Visible' : 'Masqué' }}
                            </span>
                        </div>

                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 12rem), 1fr)); gap:var(--space-3)">
                            <div class="field">
                                <label for="product_{{ $product->id }}_name">Nom</label>
                                <input id="product_{{ $product->id }}_name" name="name" type="text" value="{{ $product->name }}" required>
                            </div>

                            <div class="field">
                                <label for="product_{{ $product->id }}_price">Prix</label>
                                <input id="product_{{ $product->id }}_price" name="price" type="number" min="0" step="0.01" value="{{ $product->price }}" required>
                            </div>

                            <div class="field">
                                <label for="product_{{ $product->id }}_category">Catégorie</label>
                                <select id="product_{{ $product->id }}_category" name="category_id" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected($product->category_id === $category->id)>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label for="product_{{ $product->id }}_stock">Stock</label>
                                <input id="product_{{ $product->id }}_stock" name="stock_quantity" type="number" min="0" value="{{ $product->stock_quantity ?? 0 }}">
                            </div>
                        </div>

                        <div class="field">
                            <label for="product_{{ $product->id }}_description">Description</label>
                            <textarea id="product_{{ $product->id }}_description" name="description" rows="2">{{ $product->description }}</textarea>
                        </div>

                        <div class="cluster">
                            <label class="cluster">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($product->is_active)>
                                <span>Actif</span>
                            </label>

                            <label class="cluster">
                                <input type="hidden" name="is_available" value="0">
                                <input type="checkbox" name="is_available" value="1" @checked($product->is_available)>
                                <span>Disponible</span>
                            </label>

                            <label class="cluster">
                                <input type="hidden" name="track_stock" value="0">
                                <input type="checkbox" name="track_stock" value="1" @checked($product->track_stock)>
                                <span>Stock suivi</span>
                            </label>

                            <button class="button button--primary" type="submit">Enregistrer / حفظ</button>
                        </div>
                    </form>
                @empty
                    <p class="staff-empty">Aucun produit / لا توجد منتجات</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
