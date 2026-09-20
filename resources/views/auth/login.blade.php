@extends('layouts.auth')

@section('title', 'Connexion / تسجيل الدخول')

@section('content')
    <section class="stack" style="max-width:30rem; margin-inline:auto">
        <div>
            <h1>Connexion / تسجيل الدخول</h1>
            <p class="muted">Accédez à la file de service et aux commandes.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert--error" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form class="stack" method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="email">Email / البريد الإلكتروني</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </div>

            <div class="field">
                <label for="password">Mot de passe / كلمة السر</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>

            <label class="cluster" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1">
                <span>Se souvenir de moi / تذكرني</span>
            </label>

            <button class="button button--primary" type="submit">Se connecter / دخول</button>
        </form>
    </section>
@endsection
