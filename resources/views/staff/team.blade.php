@extends('layouts.staff')

@section('title', 'Équipe / الفريق')
@section('page_heading', 'Équipe / الفريق')

@push('styles')
    @vite('resources/css/staff.css')
@endpush

@section('content')
    <div class="staff-page">
        <section class="staff-page__intro">
            <div>
                <p class="staff-eyebrow">Accès et rôles / الصلاحيات والأدوار</p>
                <h2>Gérez votre équipe</h2>
                <p class="muted">
                    Ajoutez des serveurs et désactivez les comptes qui ne doivent plus accéder au café.
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
                        <p class="staff-eyebrow">Nouveau membre / عضو جديد</p>
                        <h2>Ajouter un serveur</h2>
                    </div>
                </div>

                <form class="stack" method="POST" action="{{ route('staff.team.store') }}">
                    @csrf

                    <div class="field">
                        <label for="member_name">Nom / الاسم</label>
                        <input id="member_name" name="name" type="text" value="{{ old('name') }}" required>
                    </div>

                    <div class="field">
                        <label for="member_email">Email / البريد الإلكتروني</label>
                        <input id="member_email" name="email" type="email" value="{{ old('email') }}" required>
                    </div>

                    <div class="field">
                        <label for="member_password">Mot de passe initial / كلمة السر</label>
                        <input id="member_password" name="password" type="password" autocomplete="new-password" required>
                    </div>

                    <div class="field">
                        <label for="member_password_confirmation">Confirmer / تأكيد</label>
                        <input id="member_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>

                    <button class="button button--primary" type="submit">
                        Ajouter / إضافة
                    </button>
                </form>
            </section>

            <section class="staff-panel">
                <div class="staff-panel__heading">
                    <div>
                        <p class="staff-eyebrow">Membres du café / أعضاء المقهى</p>
                        <h2>{{ $members->count() }} compte(s)</h2>
                    </div>
                </div>

                <div class="stack">
                    @forelse ($members as $member)
                        <article class="staff-order-card">
                            <div class="staff-order-card__header">
                                <div>
                                    <strong>{{ $member->name }}</strong>
                                    <p class="muted">{{ $member->email }}</p>
                                </div>

                                <span class="status" data-status="{{ $member->is_active ? 'active' : 'closed' }}">
                                    {{ $member->is_active ? 'Actif' : 'Désactivé' }}
                                </span>
                            </div>

                            <div class="staff-order-card__meta">
                                <span>{{ $member->role?->name === 'manager' ? 'Manager' : 'Serveur' }}</span>
                                <span>{{ $member->last_login_at?->format('d/m/Y H:i') ?? 'Jamais connecté' }}</span>
                            </div>

                            @if ($member->isManager())
                                <p class="muted">Compte propriétaire / حساب المالك</p>
                            @else
                                <form method="POST" action="{{ route('staff.team.status', $member->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $member->is_active ? '0' : '1' }}">
                                    <button class="button button--quiet" type="submit">
                                        {{ $member->is_active ? 'Désactiver / تعطيل' : 'Activer / تفعيل' }}
                                    </button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p class="staff-empty">Aucun membre trouvé / لا يوجد أعضاء</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
