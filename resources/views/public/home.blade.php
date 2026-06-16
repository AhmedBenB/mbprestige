@extends('layouts.app')

@section('title', 'MBPRESTIGE - Achetez des vehicules professionnels en gros')
@section('meta_description', 'Plateforme B2B d\'achat de vehicules d\'occasion. Encheres ouvertes, blind auctions, prix fixes. +5 000 vehicules disponibles.')

@section('content')

{{-- Hero --}}
<section class="relative bg-[#0a0a0a] text-white py-20 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-[#111111] to-[#0a0a0a]"></div>
    <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-[#d4af37] via-[#b8911f] to-transparent"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <div class="inline-flex items-center gap-2 bg-[#d4af37]/10 border border-[#d4af37]/30 text-[#d4af37] text-xs font-semibold px-3 py-1.5 rounded-full mb-6">
                <span class="w-1.5 h-1.5 bg-[#d4af37] rounded-full animate-pulse"></span>
                Plateforme B2B professionnelle
            </div>
            <h1 class="text-4xl md:text-5xl font-bold leading-tight mb-4">
                La marketplace <span class="text-[#d4af37]">automobile</span><br>pour les professionnels
            </h1>
            <p class="text-gray-400 text-lg mb-8">
                Accedez a {{ $stats['total_vehicles'] }} vehicules d'occasion. Encheres, prix fixes, stock partenaire.
                Simple, rapide, transparent.
            </p>

            {{-- Barre de recherche rapide --}}
            <form action="{{ route('catalog.index') }}" method="GET"
                  class="bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl p-4 flex flex-wrap gap-3">
                <select name="make" class="flex-1 min-w-32 bg-[#222] border border-[#333] rounded-lg px-3 py-2 text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]">
                    <option value="">Toutes les marques</option>
                    @foreach(\App\Models\ExternalListing::query()->where('status', 'published')->whereNotNull('make')->distinct()->orderBy('make')->pluck('make') as $make)
                        <option value="{{ $make }}">{{ $make }}</option>
                    @endforeach
                </select>
                <select name="fuel" class="flex-1 min-w-32 bg-[#222] border border-[#333] rounded-lg px-3 py-2 text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]">
                    <option value="">Tout carburant</option>
                    <option value="Diesel">Diesel</option>
                    <option value="Essence">Essence</option>
                    <option value="Hybride">Hybride</option>
                    <option value="Electrique">Electrique</option>
                </select>
                <input type="number" name="year_min" min="1990" max="{{ date('Y') }}" placeholder="Année minimum"
                       class="flex-1 min-w-32 bg-[#222] border border-[#333] rounded-lg px-3 py-2 text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]">
                <button type="submit"
                        class="bg-[#d4af37] hover:bg-[#b8911f] text-black font-bold px-6 py-2 rounded-lg text-sm transition-colors">
                    Rechercher
                </button>
            </form>
        </div>
    </div>
</section>

{{-- Stats --}}
<section class="bg-[#111111] border-b border-[#2a2a2a]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-wrap gap-8 justify-center text-center">
        <div>
            <div class="text-3xl font-bold text-[#d4af37]">{{ number_format($stats['total_vehicles']) }}</div>
            <div class="text-sm text-gray-500 mt-1">Vehicules disponibles</div>
        </div>
        <div>
            <div class="text-3xl font-bold text-[#d4af37]">{{ $stats['live_auctions'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Encheres en cours</div>
        </div>
        <div>
            <div class="text-3xl font-bold text-[#d4af37]">{{ $stats['brands'] }}</div>
            <div class="text-sm text-gray-500 mt-1">Marques referencees</div>
        </div>
        <div>
            <div class="text-3xl font-bold text-[#d4af37]">100%</div>
            <div class="text-sm text-gray-500 mt-1">Professionnels verifies</div>
        </div>
    </div>
</section>

{{-- 4 modes d'achat --}}
<section class="py-14 bg-[#0d0d0d]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-center text-white mb-2">4 façons d'acheter</h2>
        <p class="text-center text-gray-500 mb-10 text-sm">Choisissez le mode qui correspond a votre strategie d'achat.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach([
                ['icon' => '🔒', 'title' => 'Enchere blind', 'desc' => 'Soumettez votre meilleur prix sans voir les autres offres. Modifiable tant que l\'enchere est ouverte.', 'href' => route('catalog.auctions')],
                ['icon' => '📈', 'title' => 'Enchere ouverte', 'desc' => 'Suivez la meilleure offre en temps reel et surencherissez. L\'enchere la plus haute l\'emporte.', 'href' => route('catalog.auctions')],
                ['icon' => '🏷️', 'title' => 'Prix fixe', 'desc' => 'Achetez immediatement au prix affiche ou faites une offre legerement inferieure.', 'href' => route('catalog.fixed_prices')],
                ['icon' => '🚗', 'title' => 'Notre stock', 'desc' => 'Vehicules disponibles immediatement depuis notre propre stock. Livraison rapide garantie.', 'href' => route('catalog.stock')],
            ] as $mode)
            <a href="{{ $mode['href'] }}"
               class="bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl p-6 hover:border-[#d4af37] hover:shadow-lg hover:shadow-[#d4af37]/5 transition-all group block">
                <div class="text-3xl mb-3">{{ $mode['icon'] }}</div>
                <h3 class="font-semibold text-white mb-2 group-hover:text-[#d4af37] transition-colors">{{ $mode['title'] }}</h3>
                <p class="text-sm text-gray-500 leading-relaxed">{{ $mode['desc'] }}</p>
            </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Encheres live --}}
@if($liveAuctions->isNotEmpty())
<section class="py-14 bg-[#111111]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                    <span class="inline-block w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                    Encheres en cours
                </h2>
                <p class="text-gray-500 text-sm mt-1">Se terminent bientot - ne manquez pas ces opportunites</p>
            </div>
            <a href="{{ route('catalog.auctions') }}"
               class="text-[#d4af37] text-sm font-semibold hover:text-[#e8c96d]">Voir toutes →</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($liveAuctions as $listing)
                @include('components.external-listing-card', ['listing' => $listing])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Dernieres annonces --}}
@if($latestListings->isNotEmpty())
<section class="py-14 bg-[#0d0d0d]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-white">Dernieres annonces</h2>
            <a href="{{ route('catalog.index') }}"
               class="text-[#d4af37] text-sm font-semibold hover:text-[#e8c96d]">Tout voir →</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
            @foreach($latestListings as $listing)
                @include('components.external-listing-card', ['listing' => $listing])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Comment ca marche --}}
<section class="py-14 bg-[#111111]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-center text-white mb-2">Comment ca marche ?</h2>
        <p class="text-center text-gray-500 text-sm mb-10">Un processus simple en 4 etapes</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            @foreach([
                ['step'=>'1','title'=>'Inscrivez-vous','desc'=>'Creez votre compte professionnel gratuitement avec votre code de parrainage.'],
                ['step'=>'2','title'=>'Recherchez','desc'=>'Utilisez nos filtres avances pour trouver les vehicules qui correspondent a vos criteres.'],
                ['step'=>'3','title'=>'Encherissez ou achetez','desc'=>'Proposez une offre, participez a une enchere ou achetez directement au prix fixe.'],
                ['step'=>'4','title'=>'Receptionnez','desc'=>'Choisissez entre notre livraison ou l\'enlevement par votre transporteur.'],
            ] as $step)
            <div class="text-center">
                <div class="w-12 h-12 bg-[#d4af37] text-black rounded-full flex items-center justify-center text-xl font-bold mx-auto mb-4">
                    {{ $step['step'] }}
                </div>
                <h3 class="font-semibold text-white mb-2">{{ $step['title'] }}</h3>
                <p class="text-sm text-gray-500 leading-relaxed">{{ $step['desc'] }}</p>
            </div>
            @endforeach
        </div>
        <div class="text-center mt-10">
            <a href="{{ route('how_it_works') }}"
               class="inline-block border border-[#d4af37] text-[#d4af37] font-semibold px-6 py-2.5 rounded-lg hover:bg-[#d4af37] hover:text-black transition-colors text-sm">
                En savoir plus
            </a>
        </div>
    </div>
</section>

{{-- CTA inscription --}}
<section class="bg-gradient-to-r from-[#d4af37] to-[#b8911f] text-black py-14">
    <div class="max-w-3xl mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-4">Pret a rejoindre la plateforme ?</h2>
        <p class="text-black/70 mb-8">
            Accedez a notre catalogue complet, participez aux encheres et gerez vos achats depuis votre espace personnel.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('register') }}"
               class="bg-black text-[#d4af37] font-bold px-8 py-3 rounded-xl hover:bg-[#111] transition-colors">
                Creer mon compte
            </a>
            <a href="{{ route('professionals') }}"
               class="border-2 border-black text-black font-semibold px-8 py-3 rounded-xl hover:bg-black/10 transition-colors">
                En savoir plus
            </a>
        </div>
    </div>
</section>

@endsection
