@extends('layouts.app')

@section('title', 'MBPRESTIGE – Achetez des véhicules professionnels en gros')
@section('meta_description', 'Plateforme B2B d\'achat de véhicules d\'occasion. Enchères ouvertes, blind auctions, prix fixes. +5 000 véhicules disponibles.')

@section('content')

{{-- Hero --}}
<section class="bg-gradient-to-br from-blue-900 to-blue-700 text-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <h1 class="text-4xl md:text-5xl font-bold leading-tight mb-4">
                La marketplace B2B automobile pour les professionnels
            </h1>
            <p class="text-blue-100 text-lg mb-8">
                Accédez à {{ $stats['total_vehicles'] }} véhicules d'occasion. Enchères, prix fixes, stock partenaire.
                Simple, rapide, transparent.
            </p>

            {{-- Barre de recherche rapide --}}
            <form action="{{ route('catalog.index') }}" method="GET"
                  class="bg-white rounded-xl p-4 flex flex-wrap gap-3 shadow-xl">
                <select name="make" class="flex-1 min-w-32 border border-gray-200 rounded-lg px-3 py-2 text-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Toutes les marques</option>
                    @foreach(\App\Models\Vehicle::distinct()->orderBy('make')->pluck('make') as $make)
                        <option value="{{ $make }}">{{ $make }}</option>
                    @endforeach
                </select>
                <select name="fuel" class="flex-1 min-w-32 border border-gray-200 rounded-lg px-3 py-2 text-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Tout carburant</option>
                    <option value="Diesel">Diesel</option>
                    <option value="Essence">Essence</option>
                    <option value="Hybride">Hybride</option>
                    <option value="Electrique">Électrique</option>
                </select>
                <input type="number" name="price_max" placeholder="Prix max (€)"
                       class="flex-1 min-w-32 border border-gray-200 rounded-lg px-3 py-2 text-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit"
                        class="bg-blue-700 hover:bg-blue-800 text-white font-semibold px-6 py-2 rounded-lg text-sm">
                    Rechercher
                </button>
            </form>
        </div>
    </div>
</section>

{{-- Stats --}}
<section class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-wrap gap-8 justify-center text-center">
        <div>
            <div class="text-3xl font-bold text-blue-700">{{ number_format($stats['total_vehicles']) }}</div>
            <div class="text-sm text-gray-500 mt-1">Véhicules disponibles</div>
        </div>
        <div>
            <div class="text-3xl font-bold text-blue-700">{{ $stats['live_auctions'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Enchères en cours</div>
        </div>
        <div>
            <div class="text-3xl font-bold text-blue-700">{{ $stats['brands'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Marques référencées</div>
        </div>
        <div>
            <div class="text-3xl font-bold text-blue-700">100%</div>
            <div class="text-sm text-gray-500 mt-1">Professionnels vérifiés</div>
        </div>
    </div>
</section>

{{-- 4 modes d'achat --}}
<section class="py-14 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-center mb-2">4 façons d'acheter</h2>
        <p class="text-center text-gray-500 mb-10 text-sm">Choisissez le mode qui correspond à votre stratégie d'achat.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach([
                ['icon' => '🔒', 'title' => 'Enchère blind', 'desc' => 'Soumettez votre meilleur prix sans voir les autres offres. Modifiable tant que l\'enchère est ouverte.', 'href' => route('catalog.auctions'), 'color' => 'blue'],
                ['icon' => '📈', 'title' => 'Enchère ouverte', 'desc' => 'Suivez la meilleure offre en temps réel et surenchérissez. L\'enchère la plus haute l\'emporte.', 'href' => route('catalog.auctions'), 'color' => 'indigo'],
                ['icon' => '🏷️', 'title' => 'Prix fixe', 'desc' => 'Achetez immédiatement au prix affiché ou faites une offre légèrement inférieure.', 'href' => route('catalog.fixed_prices'), 'color' => 'emerald'],
                ['icon' => '🚗', 'title' => 'Notre stock', 'desc' => 'Véhicules disponibles immédiatement depuis notre propre stock. Livraison rapide garantie.', 'href' => route('catalog.stock'), 'color' => 'violet'],
            ] as $mode)
            <a href="{{ $mode['href'] }}"
               class="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-md hover:border-{{ $mode['color'] }}-300 transition-all group">
                <div class="text-3xl mb-3">{{ $mode['icon'] }}</div>
                <h3 class="font-semibold text-gray-900 mb-2 group-hover:text-{{ $mode['color'] }}-700">{{ $mode['title'] }}</h3>
                <p class="text-sm text-gray-500 leading-relaxed">{{ $mode['desc'] }}</p>
            </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Enchères live --}}
@if($liveAuctions->isNotEmpty())
<section class="py-14 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-2xl font-bold flex items-center gap-2">
                    <span class="inline-block w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                    Enchères en cours
                </h2>
                <p class="text-gray-500 text-sm mt-1">Se terminent bientôt – ne manquez pas ces opportunités</p>
            </div>
            <a href="{{ route('catalog.auctions') }}"
               class="text-blue-700 text-sm font-semibold hover:underline">Voir toutes →</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($liveAuctions as $listing)
                @include('components.listing-card', ['listing' => $listing])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Dernières annonces --}}
@if($latestListings->isNotEmpty())
<section class="py-14 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold">Dernières annonces</h2>
            <a href="{{ route('catalog.index') }}"
               class="text-blue-700 text-sm font-semibold hover:underline">Tout voir →</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
            @foreach($latestListings as $listing)
                @include('components.listing-card', ['listing' => $listing])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Comment ça marche --}}
<section class="py-14 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-center mb-2">Comment ça marche ?</h2>
        <p class="text-center text-gray-500 text-sm mb-10">Un processus simple en 4 étapes</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            @foreach([
                ['step'=>'1','title'=>'Inscrivez-vous','desc'=>'Créez votre compte professionnel gratuitement et faites vérifier votre entreprise.'],
                ['step'=>'2','title'=>'Recherchez','desc'=>'Utilisez nos filtres avancés pour trouver les véhicules qui correspondent à vos critères.'],
                ['step'=>'3','title'=>'Enchérissez ou achetez','desc'=>'Proposez une offre, participez à une enchère ou achetez directement au prix fixe.'],
                ['step'=>'4','title'=>'Réceptionnez','desc'=>'Choisissez entre notre livraison ou l\'enlèvement par votre transporteur.'],
            ] as $step)
            <div class="text-center">
                <div class="w-12 h-12 bg-blue-700 text-white rounded-full flex items-center justify-center text-xl font-bold mx-auto mb-4">
                    {{ $step['step'] }}
                </div>
                <h3 class="font-semibold mb-2">{{ $step['title'] }}</h3>
                <p class="text-sm text-gray-500 leading-relaxed">{{ $step['desc'] }}</p>
            </div>
            @endforeach
        </div>
        <div class="text-center mt-10">
            <a href="{{ route('how_it_works') }}"
               class="inline-block border border-blue-700 text-blue-700 font-semibold px-6 py-2.5 rounded-lg hover:bg-blue-700 hover:text-white transition-colors text-sm">
                En savoir plus
            </a>
        </div>
    </div>
</section>

{{-- CTA inscription --}}
<section class="bg-blue-700 text-white py-14">
    <div class="max-w-3xl mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-4">Prêt à rejoindre la plateforme ?</h2>
        <p class="text-blue-100 mb-8">
            Accédez à notre catalogue complet, participez aux enchères et gérez vos achats depuis votre espace personnel.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('register') }}"
               class="bg-white text-blue-700 font-bold px-8 py-3 rounded-xl hover:bg-blue-50 transition-colors">
                Créer mon compte
            </a>
            <a href="{{ route('professionals') }}"
               class="border border-white text-white font-semibold px-8 py-3 rounded-xl hover:bg-blue-600 transition-colors">
                En savoir plus
            </a>
        </div>
    </div>
</section>

@endsection
