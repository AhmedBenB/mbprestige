@extends('layouts.app')
@section('title', 'Inscription - MBPRESTIGE')

@section('content')
<div class="min-h-screen bg-[#0a0a0a] flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-[#d4af37] font-bold text-xl">
                <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/></svg>
                MBPRESTIGE
            </a>
            <h1 class="text-2xl font-bold mt-4 text-white">Créer un compte</h1>
            <p class="text-gray-500 text-sm mt-1">Inscription avec code de parrainage</p>
        </div>

        <div class="bg-[#1a1a1a] rounded-2xl border border-[#2a2a2a] p-8">
            @if($errors->any())
                <div class="bg-red-900/30 border border-red-600 rounded-lg p-3 mb-6 text-sm text-red-400">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @php
                $defaultCode = old('phone_country_code', '+33');
                $popularCodes = [
                    ['label' => 'France', 'code' => 33],
                    ['label' => 'Belgique', 'code' => 32],
                    ['label' => 'Suisse', 'code' => 41],
                    ['label' => 'Canada', 'code' => 1],
                    ['label' => 'États-Unis', 'code' => 1],
                    ['label' => 'Royaume-Uni', 'code' => 44],
                    ['label' => 'Allemagne', 'code' => 49],
                    ['label' => 'Espagne', 'code' => 34],
                    ['label' => 'Italie', 'code' => 39],
                    ['label' => 'Maroc', 'code' => 212],
                    ['label' => 'Algérie', 'code' => 213],
                    ['label' => 'Tunisie', 'code' => 216],
                ];
                $popularCodeValues = array_values(array_unique(array_map(static fn (array $row): int => $row['code'], $popularCodes)));
            @endphp

            <form method="POST" action="{{ route('register.post') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">Prénom *</label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" required
                               class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">Nom *</label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" required
                               class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Date de naissance</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"
                           max="{{ now()->toDateString() }}"
                           class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Numéro de téléphone *</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <select name="phone_country_code" required
                                class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]">
                            <optgroup label="Pays fréquents">
                                @foreach($popularCodes as $item)
                                    @php $value = '+' . $item['code']; @endphp
                                    <option value="{{ $value }}" @selected($defaultCode === $value)>{{ $item['label'] }} ({{ $value }})</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Tous les indicatifs (+1 à +998)">
                                @for($dialCode = 1; $dialCode <= 998; $dialCode++)
                                    @continue(in_array($dialCode, $popularCodeValues, true))
                                    @php $value = '+' . $dialCode; @endphp
                                    <option value="{{ $value }}" @selected($defaultCode === $value)>{{ $value }}</option>
                                @endfor
                            </optgroup>
                        </select>

                        <div class="sm:col-span-2">
                            <input type="tel" name="phone_local" value="{{ old('phone_local') }}" required
                                   inputmode="numeric" pattern="[0-9\s().-]{4,20}"
                                   class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]"
                                   placeholder="Numéro local (ex: 6 12 34 56 78)">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Email *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]"
                           placeholder="vous@example.com">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Mot de passe *</label>
                    <div class="relative">
                        <input id="register_password" type="password" name="password" required minlength="8"
                               class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-4 pr-12 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]"
                               placeholder="8 caractères minimum">
                        <button type="button"
                                data-toggle-password
                                data-target="register_password"
                                class="absolute inset-y-0 right-0 inline-flex items-center justify-center w-12 text-gray-500 hover:text-[#d4af37]"
                                aria-label="Afficher le mot de passe">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Confirmation du mot de passe *</label>
                    <div class="relative">
                        <input id="register_password_confirmation" type="password" name="password_confirmation" required
                               class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-4 pr-12 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]"
                               placeholder="Retapez votre mot de passe">
                        <button type="button"
                                data-toggle-password
                                data-target="register_password_confirmation"
                                class="absolute inset-y-0 right-0 inline-flex items-center justify-center w-12 text-gray-500 hover:text-[#d4af37]"
                                aria-label="Afficher la confirmation du mot de passe">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="border border-[#d4af37]/30 bg-[#d4af37]/5 rounded-xl p-4">
                    <label class="block text-sm font-medium text-[#d4af37] mb-1.5">Code de parrainage *</label>
                    <input type="text" name="referral_code" value="{{ old('referral_code') }}" required
                           class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37] uppercase tracking-widest"
                           placeholder="Entrez votre code">
                    <p class="text-xs text-gray-500 mt-1.5">Un code de parrainage est requis pour acceder a la plateforme.</p>
                </div>

                <p class="text-xs text-gray-600">* Champs obligatoires.</p>

                <button type="submit"
                        class="w-full bg-[#d4af37] hover:bg-[#b8911f] text-black font-bold py-3 rounded-xl text-sm transition-colors">
                    Créer mon compte
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-500">
                Déjà inscrit ?
                <a href="{{ route('login') }}" class="text-[#d4af37] font-semibold hover:text-[#e8c96d]">Se connecter</a>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    });
    document.querySelectorAll('input[name="referral_code"]').forEach(function(input) {
        input.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
    });
});
</script>
@endsection
