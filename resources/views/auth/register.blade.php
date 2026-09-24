@extends('layouts.auth')

@section('title', 'Créer votre café / إنشاء المقهى')

@section('content')
    <section class="stack" style="max-width:42rem; margin-inline:auto">
        <div>
            <p class="muted">Café Manager · SaaS</p>
            <h1>Créer votre espace / إنشاء فضاء المقهى</h1>
            <p class="muted">
                Créez votre café et configurez vos premières tables et produits.
            </p>
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

        <form class="stack" method="POST" action="{{ route('register.store') }}">
            @csrf

            <div class="surface stack" style="padding:var(--space-5)">
                <h2>Votre café / المقهى ديالك</h2>

                <div class="field">
                    <label for="cafe_name">Nom du café / اسم المقهى</label>
                    <input id="cafe_name" name="cafe_name" type="text" value="{{ old('cafe_name') }}" required autofocus>
                </div>

                <div class="cluster">
                    <div class="field" style="flex:1 1 14rem">
                        <label for="city">Ville / المدينة</label>
                        <input id="city" name="city" type="text" value="{{ old('city') }}">
                    </div>

                    <div class="field" style="flex:1 1 14rem">
                        <label for="phone">Téléphone / الهاتف</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}">
                    </div>
                </div>
            </div>

            <div class="surface stack" style="padding:var(--space-5)">
                <h2>Votre compte manager / حساب المدير</h2>

                <div class="field">
                    <label for="name">Nom complet / الاسم الكامل</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required>
                </div>

                <div class="field">
                    <label for="email">Email / البريد الإلكتروني</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                </div>

                <div class="cluster">
                    <div class="field" style="flex:1 1 14rem">
                        <label for="password">Mot de passe / كلمة السر</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required>
                    </div>

                    <div class="field" style="flex:1 1 14rem">
                        <label for="password_confirmation">Confirmer / تأكيد كلمة السر</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                </div>
            </div>

            <button class="button button--primary" type="submit">
                Créer mon espace / إنشاء الفضاء
            </button>
        </form>

        <p class="muted">
            Déjà inscrit ?
            <a href="{{ route('login') }}">Se connecter / دخول</a>
        </p>
    </section>
@endsection
