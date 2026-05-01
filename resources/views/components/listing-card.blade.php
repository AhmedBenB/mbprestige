{{--
    Composant : listing-card
    Usage : @include('components.listing-card', ['listing' => $listing])
--}}
@php
    $vehicle = $listing->vehicle;
    $image   = $listing->coverImage;
    $auction = $listing->auction;
    $isAuction = $listing->isAuction();
    $isAuctionPriceHidden = $isAuction && auth()->guest();
    $price = $listing->current_bid ?? $listing->buy_now_price ?? $listing->starting_price;
@endphp

<a href="{{ route('vehicles.show', $listing) }}"
   class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all group block">

    {{-- Image --}}
    <div class="relative aspect-[4/3] bg-gray-100 overflow-hidden">
        @if($image && $image->url())
            <img src="{{ $image->url() }}"
                 alt="{{ $listing->title }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        @else
            <div class="w-full h-full flex items-center justify-center text-gray-300">
                <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/>
                </svg>
            </div>
        @endif

        {{-- Badge type --}}
        <div class="absolute top-2 left-2 flex gap-1.5">
            @switch($listing->listing_type->value)
                @case('auction_open')
                    <span class="bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">Enchère ouverte</span>
                    @break
                @case('auction_blind')
                    <span class="bg-purple-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">Enchère blind</span>
                    @break
                @case('fixed_price')
                    <span class="bg-emerald-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">Prix fixe</span>
                    @break
                @case('partner_stock')
                    <span class="bg-blue-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">Stock</span>
                    @break
            @endswitch

            @if($listing->vat_deductible)
                <span class="bg-yellow-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">TVA</span>
            @endif
        </div>

        {{-- Pays --}}
        @if($vehicle->origin_country)
            <div class="absolute top-2 right-2 bg-black/50 text-white text-xs px-1.5 py-0.5 rounded font-medium">
                {{ $vehicle->origin_country }}
            </div>
        @endif

        {{-- Chrono pour enchères --}}
        @if($isAuction && $auction && $listing->ends_at)
            <div class="absolute bottom-2 left-2 right-2">
                <div class="bg-black/70 text-white text-xs px-2 py-1 rounded-lg flex items-center gap-1.5"
                     x-data="auctionTimer({
                         server_time_utc: '{{ now()->utc()->toIso8601String() }}',
                         ends_at_utc: '{{ $listing->ends_at->utc()->toIso8601String() }}',
                         auction_status: '{{ $listing->auction_status?->value ?? 'live' }}'
                     })">
                    <span class="w-1.5 h-1.5 bg-red-400 rounded-full animate-pulse"></span>
                    <span x-text="remainingLabel">{{ $listing->ends_at->diffForHumans() }}</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Infos --}}
    <div class="p-4">
        <h3 class="font-semibold text-gray-900 text-sm leading-tight mb-2 line-clamp-2 group-hover:text-blue-700">
            {{ $listing->title }}
        </h3>

        {{-- Specs rapides --}}
        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 mb-3">
            @if($vehicle->registration_year)
                <span>{{ $vehicle->registration_year }}</span>
            @endif
            @if($vehicle->mileage)
                <span>{{ number_format($vehicle->mileage) }} km</span>
            @endif
            @if($vehicle->fuel_type)
                <span>{{ $vehicle->fuel_type }}</span>
            @endif
            @if($vehicle->gearbox)
                <span>{{ $vehicle->gearbox }}</span>
            @endif
        </div>

        {{-- Prix --}}
        <div class="flex items-center justify-between mt-auto">
            <div>
                @if($isAuctionPriceHidden)
                    <div class="text-sm text-gray-500 font-medium">
                        Prix masqué (enchère)
                    </div>
                    <div class="text-xs text-blue-700 mt-1">
                        Connectez-vous pour voir les montants.
                    </div>
                @elseif($price)
                    <div class="text-lg font-bold text-gray-900">
                        {{ number_format($price, 0, ',', ' ') }} €
                    </div>
                    @if($isAuction && $listing->current_bid)
                        <div class="text-xs text-gray-400">Meilleure offre · {{ $listing->bid_count }} offre(s)</div>
                    @elseif($isAuction)
                        <div class="text-xs text-gray-400">Prix de départ</div>
                    @endif
                @else
                    <div class="text-sm text-gray-400 italic">Prix sur demande</div>
                @endif
            </div>

            {{-- Favori --}}
            @auth
            <button onclick="event.preventDefault(); event.stopPropagation();"
                    class="text-gray-300 hover:text-red-500 transition-colors p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </button>
            @endauth
        </div>
    </div>
</a>
