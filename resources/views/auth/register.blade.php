@extends('layouts.app')
@section('title', 'Inscription – MBPRESTIGE')

@section('content')
<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-lg">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-blue-700 font-bold text-xl">
                <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/></svg>
                MBPRESTIGE
            </a>
            <h1 class="text-2xl font-bold mt-4 text-gray-900">Créer votre compte professionnel</h1>
            <p class="text-gray-500 text-sm mt-1">Accédez à +5 000 véhicules d'occasion</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-6 text-sm text-red-700">
                    @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('register.post') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Prénom</label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" required
                               class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nom</label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" required
                               class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email professionnel</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="vous@societe.fr">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Téléphone</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="+33 6 00 00 00 00">
                </div>

                <hr class="border-gray-100">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Votre société</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nom de la société</label>
                    <input type="text" name="company_name" value="{{ old('company_name') }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Numéro TVA</label>
                        <input type="text" name="vat_number" value="{{ old('vat_number') }}"
                               class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="FR12345678901">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Pays</label>
                        <select name="country" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @foreach(['FR'=>'France','BE'=>'Belgique','DE'=>'Allemagne','NL'=>'Pays-Bas','ES'=>'Espagne','IT'=>'Italie','PT'=>'Portugal','PL'=>'Pologne','RO'=>'Roumanie'] as $code => $name)
                                <option value="{{ $code }}" @selected(old('country','FR')===$code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <hr class="border-gray-100">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Sécurité</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Mot de passe</label>
                    <input type="password" name="password" required minlength="8"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="8 caractères minimum">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirmer le mot de passe</label>
                    <input type="password" name="password_confirmation" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <label class="flex items-start gap-2 text-sm text-gray-600 cursor-pointer">
                    <input type="checkbox" name="accept_terms" required class="rounded text-blue-600 mt-0.5 flex-shrink-0">
                    <span>
                        J'accepte les
                        <a href="{{ route('cgv') }}" class="text-blue-700 hover:underline" target="_blank">Conditions Générales</a>
                        et la
                        <a href="{{ route('privacy') }}" class="text-blue-700 hover:underline" target="_blank">Politique de confidentialité</a>.
                    </span>
                </label>

                <button type="submit"
                        class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-3 rounded-xl text-sm transition-colors">
                    Créer mon compte
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-500">
                Déjà inscrit ?
                <a href="{{ route('login') }}" class="text-blue-700 font-semibold hover:underline">Se connecter</a>
            </div>
        </div>
    </div>
</div>
@endsection
