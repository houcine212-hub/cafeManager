@extends('layouts.auth')

@section('title', 'Configuration / إعداد المقهى')

@section('content')
    <div class="stack" style="max-width:74rem; margin-inline:auto">
        <div class="split">
            <div>
                <p class="muted">Café Manager · Onboarding</p>
                <h1>Configurez votre café / جهّز المقهى ديالك</h1>
                <p class="muted">
                    Ajoutez vos tables, catégories et produits. Chaque table reçoit automatiquement un QR.
                </p>
            </div>

            <a class="button button--quiet" href="{{ route('staff.dashboard') }}">
                Espace staff / فضاء الموظفين
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert--error" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(18rem,1fr)); gap:var(--space-4)">
            <section class="surface stack" style="padding:var(--space-5)">
                <div>
                    <p class="muted">01</p>
                    <h2>Tables et QR / الطاولات وQR</h2>
                </div>

                <form class="stack" method="POST" action="{{ route('onboarding.tables.store') }}">
                    @csrf

                    <div class="field">
                        <label for="table_label">Nom / الاسم</label>
                        <input id="table_label" name="label" type="text" placeholder="Table 1" value="{{ old('label') }}" required>
                    </div>

                    <div class="field">
                        <label for="table_capacity">Capacité / العدد</label>
                        <input id="table_capacity" name="capacity" type="number" min="1" max="100" value="{{ old('capacity', 2) }}">
                    </div>

                    <button class="button button--primary" type="submit">
                        Ajouter la table / زيد الطاولة
                    </button>
                </form>

                <div class="stack">
                    @forelse ($tables as $table)
                        <div class="alert">
                            <strong>{{ $table->label }}</strong>
                            <span class="muted"> · {{ $table->capacity ?? '—' }} places</span>

                            @if ($table->activeQrCode)
                                <div style="margin-top:var(--space-2); overflow-wrap:anywhere">
                                    <small>{{ request()->getSchemeAndHttpHost() . '/q/' . $table->activeQrCode->token }}</small>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="muted">Aucune table pour le moment.</p>
                    @endforelse
                </div>
            </section>

            <section class="surface stack" style="padding:var(--space-5)">
                <div>
                    <p class="muted">02</p>
                    <h2>Catégories / الفئات</h2>
                </div>

                <form class="stack" method="POST" action="{{ route('onboarding.categories.store') }}">
                    @csrf

                    <div class="field">
                        <label for="category_name">Nom / الاسم</label>
                        <input id="category_name" name="name" type="text" placeholder="Boissons chaudes" value="{{ old('name') }}" required>
                    </div>

                    <div class="field">
                        <label for="sort_order">Ordre / الترتيب</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}">
                    </div>

                    <button class="button button--primary" type="submit">
                        Ajouter la catégorie / زيد الفئة
                    </button>
                </form>

                <div class="cluster">
                    @forelse ($categories as $category)
                        <span class="status">{{ $category->name }}</span>
                    @empty
                        <p class="muted">Aucune catégorie pour le moment.</p>
                    @endforelse
                </div>
            </section>

            <section class="surface stack" style="padding:var(--space-5)">
                <div>
                    <p class="muted">03</p>
                    <h2>Produits / المنتجات</h2>
                </div>

                <form class="stack" method="POST" action="{{ route('onboarding.products.store') }}">
                    @csrf

                    <div class="field">
                        <label for="product_category_id">Catégorie / الفئة</label>
                        <select id="product_category_id" name="category_id" required>
                            <option value="">Choisir / اختار</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="product_name">Nom / الاسم</label>
                        <input id="product_name" name="name" type="text" value="{{ old('name') }}" required>
                    </div>

                    <div class="field">
                        <label for="product_price">Prix MAD / الثمن</label>
                        <input id="product_price" name="price" type="number" min="0" step="0.01" value="{{ old('price') }}" required>
                    </div>

                    <div class="field">
                        <label for="product_description">Description / الوصف</label>
                        <textarea id="product_description" name="description" rows="3">{{ old('description') }}</textarea>
                    </div>

                    <label class="cluster" for="track_stock">
                        <input id="track_stock" name="track_stock" type="checkbox" value="1" @checked(old('track_stock'))>
                        <span>Suivre le stock / تتبع المخزون</span>
                    </label>

                    <div class="field">
                        <label for="stock_quantity">Stock initial / المخزون الأولي</label>
                        <input id="stock_quantity" name="stock_quantity" type="number" min="0" value="{{ old('stock_quantity', 0) }}">
                    </div>

                    <button class="button button--primary" type="submit" @disabled($categories->isEmpty())>
                        Ajouter le produit / زيد المنتج
                    </button>
                </form>

                <div class="stack">
                    @forelse ($products as $product)
                        <div class="alert">
                            <strong>{{ $product->name }}</strong>
                            <span class="muted"> · {{ $product->price }} MAD</span>
                            <small style="display:block">{{ $product->category?->name }}</small>
                        </div>
                    @empty
                        <p class="muted">Aucun produit pour le moment.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
