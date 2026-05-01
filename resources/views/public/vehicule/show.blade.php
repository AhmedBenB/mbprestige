@extends('layouts.app')

@section('title', $listing->title . ' – MBPRESTIGE')
@section('meta_description', $listing->short_description ?? "Achetez {$listing->title}, {$listing->vehicle->mileage} km, {$listing->vehicle->fuel_type}.")

@section('content')
@php
    $isAuctionPriceHidden = $listing->isAuction() && auth()->guest();
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Fil d'Ariane --}}
    <nav class="text-xs text-gray-500 mb-6 flex items-center gap-2">
        <a href="{{ route('home') }}" class="hover:text-blue-700">Accueil</a>
        <span>/</span>
        <a href="{{ route('catalog.index') }}" class="hover:text-blue-700">Catalogue</a>
        <span>/</span>
        <a href="{{ route('brands.show', strtolower($listing->vehicle->make)) }}" class="hover:text-blue-700">{{ $listing->vehicle->make }}</a>
        <span>/</span>
        <span class="text-gray-900 truncate">{{ $listing->title }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Colonne gauche : galerie + specs --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Galerie --}}
            <div x-data="{ activeImg: 0 }" class="bg-white rounded-xl overflow-hidden border border-gray-200">
                @php $images = $listing->images; @endphp
                @if($images->isNotEmpty())
                    {{-- Image principale --}}
                    <div class="relative aspect-[16/9] bg-gray-100 overflow-hidden">
                        @foreach($images as $i => $img)
                            <img src="{{ $img->url() }}" alt="{{ $listing->title }}"
                                 class="w-full h-full object-cover absolute inset-0 transition-opacity duration-300"
                                 :class="activeImg === {{ $i }} ? 'opacity-100' : 'opacity-0'">
                        @endforeach
                        {{-- Flèches nav --}}
                        @if($images->count() > 1)
                        <button @click="activeImg = (activeImg - 1 + {{ $images->count() }}) % {{ $images->count() }}"
                                class="absolute left-3 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white w-8 h-8 rounded-full flex items-center justify-center">‹</button>
                        <button @click="activeImg = (activeImg + 1) % {{ $images->count() }}"
                                class="absolute right-3 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white w-8 h-8 rounded-full flex items-center justify-center">›</button>
                        @endif
                        {{-- Compteur --}}
                        <div class="absolute bottom-3 right-3 bg-black/50 text-white text-xs px-2 py-1 rounded-full">
                            <span x-text="activeImg + 1"></span>/{{ $images->count() }}
                        </div>
                    </div>
                    {{-- Miniatures --}}
                    @if($images->count() > 1)
                    <div class="flex gap-2 p-3 overflow-x-auto">
                        @foreach($images as $i => $img)
                            <button @click="activeImg = {{ $i }}"
                                    class="flex-shrink-0 w-16 h-12 rounded-md overflow-hidden border-2 transition-colors"
                                    :class="activeImg === {{ $i }} ? 'border-blue-600' : 'border-transparent'">
                                <img src="{{ $img->url() }}" alt="" class="w-full h-full object-cover">
                            </button>
                        @endforeach
                    </div>
                    @endif
                @else
                    <div class="aspect-[16/9] bg-gray-100 flex items-center justify-center text-gray-300">
                        <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-1.1 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                        </svg>
                    </div>
                @endif
            </div>

            {{-- Profil véhicule --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold mb-4">Fiche technique</h2>
                @php $v = $listing->vehicle; @endphp
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    @foreach([
                        'Marque' => $v->make,
                        'Modèle' => $v->model,
                        'Version' => $v->version,
                        'Carrosserie' => $v->body_type,
                        'Carburant' => $v->fuel_type,
                        'Boîte' => $v->gearbox,
                        'Cylindrée' => $v->engine_size_cc ? $v->engine_size_cc.' cc' : null,
                        'Puissance' => $v->power_hp ? $v->power_hp.' ch ('.$v->power_kw.' kW)' : null,
                        'CO₂' => $v->co2 ? $v->co2.' g/km' : null,
                        'Portes' => $v->doors,
                        'Places' => $v->seats,
                        'Couleur' => $v->color,
                        'Pays d\'origine' => $v->origin_country,
                        '1ère immat.' => $v->first_registration_date?->format('m/Y'),
                        'Kilométrage' => $v->mileage ? number_format($v->mileage).' km' : null,
                        'Norme émission' => $v->emission_class,
                    ] as $label => $value)
                        @if($value)
                        <div class="border-b border-gray-50 pb-2">
                            <div class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">{{ $label }}</div>
                            <div class="font-medium text-gray-800">{{ $value }}</div>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Description --}}
            @if($listing->long_description)
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold mb-4">Description</h2>
                <div class="prose prose-sm max-w-none text-gray-600">
                    {!! nl2br(e($listing->long_description)) !!}
                </div>
            </div>
            @endif

            {{-- Options / Équipements --}}
            @if($attributes->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold mb-4">Équipements & Options</h2>
                @php
                    $groupLabels = [
                        'high_value_options' => 'Options haute valeur',
                        'safety_security'    => 'Sécurité',
                        'multimedia'         => 'Multimédia',
                        'other_options'      => 'Autres options',
                    ];
                @endphp
                @foreach($attributes as $group => $attrs)
                <div x-data="{ open: true }" class="mb-4 border border-gray-100 rounded-lg overflow-hidden">
                    <button @click="open = !open"
                            class="w-full flex justify-between items-center px-4 py-3 bg-gray-50 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                        {{ $groupLabels[$group] ?? ucfirst(str_replace('_', ' ', $group)) }}
                        <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-4 py-3 grid grid-cols-2 gap-2">
                        @foreach($attrs as $attr)
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            {{ $attr->attribute_name }}
                            @if($attr->attribute_value)
                                <span class="text-gray-400">: {{ $attr->attribute_value }}</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Documents --}}
            @if($listing->documents->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold mb-4">Documents</h2>
                <div class="space-y-2">
                    @foreach($listing->documents as $doc)
                        @if($doc->isPublic() || auth()->check())
                        <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                            <div class="flex items-center gap-2 text-sm text-gray-700">
                                <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
                                </svg>
                                {{ ucfirst(str_replace('_', ' ', $doc->type)) }}
                            </div>
                            @auth
                            <a href="{{ Storage::url($doc->file_path) }}" target="_blank"
                               class="text-xs text-blue-700 hover:underline font-medium">Télécharger</a>
                            @else
                            <span class="text-xs text-gray-400 italic">Connexion requise</span>
                            @endauth
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Colonne droite : prix + action --}}
        <div class="lg:col-span-1">
            <div class="sticky top-24 space-y-4">

                {{-- Carte prix / enchère --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">

                    {{-- Titre & type --}}
                    <div class="mb-4">
                        <h1 class="text-xl font-bold text-gray-900 leading-tight">{{ $listing->title }}</h1>
                        <div class="flex items-center gap-2 mt-2">
                            @switch($listing->listing_type->value)
                                @case('auction_open')
                                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-0.5 rounded-full">Enchère ouverte</span>
                                    @break
                                @case('auction_blind')
                                    <span class="bg-purple-100 text-purple-700 text-xs font-bold px-2 py-0.5 rounded-full">Enchère blind</span>
                                    @break
                                @case('fixed_price')
                                    <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-0.5 rounded-full">Prix fixe</span>
                                    @break
                                @case('partner_stock')
                                    <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-0.5 rounded-full">Stock partenaire</span>
                                    @break
                            @endswitch
                            @if($listing->vat_deductible)
                                <span class="bg-yellow-100 text-yellow-700 text-xs font-bold px-2 py-0.5 rounded-full">TVA récupérable</span>
                            @endif
                        </div>
                    </div>

                    {{-- Chronomètre enchère --}}
                    @if($timerPayload)
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg"
                         x-data="auctionTimer({{ json_encode($timerPayload) }})">
                        <div class="text-xs text-gray-500 mb-1 uppercase tracking-wide font-medium">
                            <span x-text="{
                                scheduled: 'Démarre dans',
                                live: 'Temps restant',
                                ending_soon: '⚠️ Se termine dans',
                                ended_waiting_validation: 'Décision vendeur sous',
                                winner_selected: 'Enchère clôturée',
                                not_awarded: 'Non attribuée',
                                cancelled: 'Annulée',
                            }[status] ?? 'Temps restant'"></span>
                        </div>
                        <div class="text-2xl font-bold text-gray-900 tabular-nums" x-text="remainingLabel">
                            {{ $listing->ends_at?->diffForHumans() }}
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            Fin : {{ $listing->ends_at?->format('d/m/Y à H:i') }} (heure locale)
                        </div>
                    </div>
                    @endif

                    {{-- Prix --}}
                    <div class="mb-5">
                        @if($isAuctionPriceHidden)
                        <div class="text-sm text-gray-500 mb-1">Prix d'enchère masqué</div>
                        <div class="text-xl font-bold text-gray-900">
                            Connectez-vous pour voir les montants
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            La visibilité des prix d'enchères est réservée aux comptes vérifiés.
                        </div>
                        @elseif($listing->current_bid)
                        <div class="text-sm text-gray-500 mb-0.5">Meilleure offre actuelle</div>
                        <div class="text-3xl font-bold text-gray-900">
                            {{ number_format($listing->current_bid, 0, ',', ' ') }} €
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $listing->bid_count }} offre(s) · min. suivante : {{ number_format($listing->minimumBid(), 0, ',', ' ') }} €</div>
                        @elseif($listing->starting_price)
                        <div class="text-sm text-gray-500 mb-0.5">Prix de départ</div>
                        <div class="text-3xl font-bold text-gray-900">
                            {{ number_format($listing->starting_price, 0, ',', ' ') }} €
                        </div>
                        @endif

                        @if(!$isAuctionPriceHidden && $listing->buy_now_price && $listing->isAuction())
                        <div class="mt-2 text-sm text-gray-500">
                            Achat immédiat : <span class="font-semibold text-emerald-700">{{ number_format($listing->buy_now_price, 0, ',', ' ') }} €</span>
                        </div>
                        @elseif(!$isAuctionPriceHidden && $listing->buy_now_price && !$listing->isAuction())
                        <div class="text-3xl font-bold text-gray-900">
                            {{ number_format($listing->buy_now_price, 0, ',', ' ') }} €
                        </div>
                        @endif

                        @if(!$isAuctionPriceHidden && $listing->estimate_price)
                        <div class="text-xs text-gray-400 mt-1">
                            Estimation marché : {{ number_format($listing->estimate_price, 0, ',', ' ') }} €
                        </div>
                        @endif
                    </div>

                    {{-- Formulaire offre / achat --}}
                    @auth
                        @if($listing->isAuction() && ($timerPayload['can_bid'] ?? false))
                        <form action="{{ route('app.bids.store', $listing) }}" method="POST" class="mb-3">
                            @csrf
                            @error('amount')
                                <div class="text-red-600 text-xs mb-2">{{ $message }}</div>
                            @enderror
                            @error('bid')
                                <div class="text-red-600 text-xs mb-2">{{ $message }}</div>
                            @enderror
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <input type="number"
                                           name="amount"
                                           value="{{ old('amount', $listing->minimumBid()) }}"
                                           min="{{ $listing->minimumBid() }}"
                                           step="100"
                                           class="w-full border border-gray-300 rounded-lg pl-3 pr-8 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('amount') border-red-400 @enderror">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">€</span>
                                </div>
                                <button type="submit"
                                        class="bg-blue-700 hover:bg-blue-800 text-white font-semibold px-4 py-2.5 rounded-lg text-sm whitespace-nowrap"
                                        onclick="return confirm('Confirmer cette offre ? {{ $listing->listing_type->value === "auction_open" ? "Les enchères ouvertes ne peuvent pas être annulées." : "" }}')">
                                    Enchérir
                                </button>
                            </div>
                            <p class="text-xs text-gray-400 mt-1.5">
                                Offre minimum : {{ number_format($listing->minimumBid(), 0, ',', ' ') }} €
                                @if($listing->listing_type->value === 'auction_open')
                                    · Non annulable
                                @else
                                    · Modifiable jusqu'à la clôture
                                @endif
                            </p>
                        </form>
                        @endif

                        @if($listing->buy_now_price)
                        <form action="{{ route('app.purchase.buy_now', $listing) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg text-sm"
                                    onclick="return confirm('Confirmer la réservation ? Vous réglerez uniquement un acompte maintenant.')">
                                Réserver (acompte uniquement)
                            </button>
                        </form>
                        @endif

                        {{-- Ajouter aux favoris --}}
                        <form action="{{ route('app.favorites.store', $listing) }}" method="POST" class="mt-3">
                            @csrf
                            <button type="submit"
                                    class="w-full border border-gray-300 text-gray-600 hover:border-red-400 hover:text-red-500 font-medium py-2 rounded-lg text-sm flex items-center justify-center gap-2 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                                Ajouter aux favoris
                            </button>
                        </form>

                    @else
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                            <p class="text-sm text-blue-800 font-medium mb-2">
                                Connectez-vous pour enchérir, acheter et voir les prix d'enchère
                            </p>
                            <div class="flex gap-2">
                                <a href="{{ route('login') }}"
                                   class="flex-1 bg-blue-700 text-white text-sm font-semibold py-2 rounded-lg hover:bg-blue-800 text-center">
                                    Connexion
                                </a>
                                <a href="{{ route('register') }}"
                                   class="flex-1 border border-blue-700 text-blue-700 text-sm font-semibold py-2 rounded-lg hover:bg-blue-50 text-center">
                                    S'inscrire
                                </a>
                            </div>
                        </div>
                    @endauth
                </div>

                {{-- Infos livraison --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-sm space-y-3">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l-1 11a2 2 0 002 2h12a2 2 0 002-2L19 8"/>
                        </svg>
                        <div>
                            <div class="font-medium text-gray-800">Livraison disponible</div>
                            <div class="text-gray-500 text-xs">Contactez-nous pour un devis transport</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <div class="font-medium text-gray-800">Enlèvement possible</div>
                            <div class="text-gray-500 text-xs">Sur présentation de l'autorisation de retrait</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <div class="font-medium text-gray-800">Paiement par virement</div>
                            <div class="text-gray-500 text-xs">Depuis le compte de votre société</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Véhicules similaires --}}
    @if($similarListings->isNotEmpty())
    <div class="mt-12">
        <h2 class="text-xl font-bold mb-6">Véhicules similaires</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach($similarListings as $similar)
                @include('components.listing-card', ['listing' => $similar])
            @endforeach
        </div>
    </div>
    @endif

</div>

{{-- Script Alpine chronomètre --}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('auctionTimer', (config) => ({
        serverTimeMs:    new Date(config.server_time_utc).getTime(),
        clientTimeAtLoad: Date.now(),
        startsAt:  config.starts_at_utc        ? new Date(config.starts_at_utc).getTime()        : null,
        endsAt:    config.ends_at_utc           ? new Date(config.ends_at_utc).getTime()           : null,
        decisionAt: config.seller_decision_deadline_at_utc
                    ? new Date(config.seller_decision_deadline_at_utc).getTime() : null,
        status: config.auction_status,
        remainingLabel: '',
        interval: null,

        init() {
            this.tick();
            this.interval = setInterval(() => this.tick(), 1000);
        },
        destroy() { clearInterval(this.interval); },

        now() {
            return Date.now() + (this.serverTimeMs - this.clientTimeAtLoad);
        },

        tick() {
            const now = this.now();

            if (this.startsAt && now < this.startsAt) {
                this.status = 'scheduled';
                this.remainingLabel = this.fmt(this.startsAt - now);
                return;
            }
            if (this.endsAt && now < this.endsAt) {
                const rem = this.endsAt - now;
                this.status = rem <= 3_600_000 ? 'ending_soon' : 'live';
                this.remainingLabel = this.fmt(rem);
                return;
            }
            if (this.decisionAt && now < this.decisionAt) {
                this.status = 'ended_waiting_validation';
                this.remainingLabel = this.fmt(this.decisionAt - now);
                return;
            }
            this.status = 'ended_waiting_validation';
            this.remainingLabel = 'Terminé';
        },

        fmt(ms) {
            const s  = Math.max(0, Math.floor(ms / 1000));
            const d  = Math.floor(s / 86400);
            const h  = Math.floor((s % 86400) / 3600);
            const m  = Math.floor((s % 3600) / 60);
            const ss = s % 60;
            const p  = n => String(n).padStart(2, '0');

            if (d > 0)  return `${d}j ${p(h)}h`;
            if (h > 0)  return `${p(h)}h ${p(m)}m`;
            if (m > 0)  return `${p(m)}m ${p(ss)}s`;
            return `${ss}s`;
        }
    }));
});
</script>
@endsection
