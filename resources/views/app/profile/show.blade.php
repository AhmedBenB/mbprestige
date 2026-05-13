@extends('layouts.app')
@section('title', 'Mon espace - MBPRESTIGE')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Mon espace</h1>
        <p class="text-sm text-gray-500 mt-1">Gere ton profil, ta securite et tes integrations.</p>
    </div>

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations personnelles</h2>
            <form method="POST" action="{{ route('app.profile.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prenom</label>
                        <input type="text" name="first_name" required
                               value="{{ old('first_name', $user->first_name) }}"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
                        <input type="text" name="last_name" required
                               value="{{ old('last_name', $user->last_name) }}"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date de naissance</label>
                    <input type="date" name="date_of_birth"
                           value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d')) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" required
                           value="{{ old('email', $user->email) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telephone</label>
                    <input type="text" name="phone" required
                           value="{{ old('phone', $user->phone) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2"
                           placeholder="+33 6 12 34 56 78">
                </div>

                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    Enregistrer le profil
                </button>
            </form>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Securite du compte</h2>
            <form method="POST" action="{{ route('app.profile.password.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe actuel</label>
                    <input type="password" name="current_password" required
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe</label>
                    <input type="password" name="password" required minlength="8"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmation</label>
                    <input type="password" name="password_confirmation" required minlength="8"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>

                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black">
                    Mettre a jour le mot de passe
                </button>
            </form>
        </section>
    </div>

    @if($user->isAdmin())
        <section class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Integration eCarsTrade</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Ces identifiants appartiennent a ton organisation et seront utilises pour les imports.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                    <div class="text-gray-500">Organisation</div>
                    <div class="font-medium text-gray-900">{{ $organization?->name ?? 'Aucune' }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                    <div class="text-gray-500">Statut auth</div>
                    <div class="font-medium text-gray-900">{{ $ecarsTradeStatusLabel }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                    <div class="text-gray-500">Dernier test</div>
                    <div class="font-medium text-gray-900">{{ $ecarsTradeCheckedAt ?? 'Jamais' }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                    <div class="text-gray-500">Actif</div>
                    <div class="font-medium text-gray-900">{{ ($ecarsTradeAccount?->is_active ?? false) ? 'Oui' : 'Non' }}</div>
                </div>
            </div>

            <form method="POST" action="{{ route('app.profile.ecarstrade.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email eCarsTrade</label>
                        <input type="email" name="login_email"
                               value="{{ old('login_email', $ecarsTradeAccount?->login_email) }}"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Identifiant eCarsTrade</label>
                        <input type="text" name="login_username"
                               value="{{ old('login_username', $ecarsTradeAccount?->login_username) }}"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe eCarsTrade</label>
                        <input type="password" name="password"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2"
                               placeholder="Laisser vide pour conserver l'existant">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
                        <input type="url" name="base_url"
                               value="{{ old('base_url', $ecarsTradeAccount?->base_url ?: $defaultEcarsTradeBaseUrl) }}"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="inline-flex items-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                        Enregistrer eCarsTrade
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('app.profile.ecarstrade.test') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Tester la connexion eCarsTrade
                </button>
            </form>
        </section>
    @endif

    <section class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Actions rapides</h2>
        <div class="flex flex-wrap gap-3 text-sm">
            <a href="{{ route('app.dashboard') }}" class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-50">Tableau de bord</a>
            <a href="{{ route('catalog.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-50">Catalogue</a>
            <a href="{{ route('app.favorites.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-50">Favoris</a>
            <a href="{{ route('app.bids.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-50">Mes offres</a>
            <a href="{{ route('app.support.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-50">Support</a>
        </div>
    </section>
</div>
@endsection
