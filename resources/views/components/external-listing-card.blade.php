@php
    $title = trim((string) ($listing->title ?: (($listing->make ?? '') . ' ' . ($listing->model ?? ''))));

    $rawImages = is_array($listing->images ?? null) ? $listing->images : [];
    $images = array_values(array_filter(array_map(fn($u) => is_string($u) && $u !== '' ? \App\Helpers\ImageProxy::url($u) : null, $rawImages)));
    $firstImage = $images[0] ?? null;

    $rawListingType = $listing->listing_type ?? null;
    $listingType = '';
    if ($rawListingType instanceof \BackedEnum) {
        $listingType = (string) $rawListingType->value;
    } elseif (is_string($rawListingType)) {
        $listingType = $rawListingType;
    } elseif (is_scalar($rawListingType)) {
        $listingType = (string) $rawListingType;
    }

    $isAuction = str_starts_with($listingType, 'auction_');
    $priceVisible = (bool) ($listing->price_visible ?? false);
    $price = $listing->price_amount;
    $estimate = $listing->latestPriceEstimate ?? null;
    $displayMargin = (float) config('ecarstrade.import.display_margin', config('ecarstrade.import.margin_min', 2000));
    $displayPrice = $price !== null ? ((float) $price + $displayMargin) : null;

    $detailsUrl = url('/annonces/' . (string) $listing->id);

    $statusRaw = $listing->status ?? null;
    $status = '';
    if ($statusRaw instanceof \BackedEnum) {
        $status = (string) $statusRaw->value;
    } elseif (is_string($statusRaw)) {
        $status = $statusRaw;
    } elseif (is_scalar($statusRaw)) {
        $status = (string) $statusRaw;
    }

    $isExpired = ($status === 'expired')
        || (!empty($listing->auction_end_at) && $listing->auction_end_at->isPast());

    $fuelValue = $listing->fuel ?? '';
    if ($fuelValue instanceof \BackedEnum) { $fuelValue = $fuelValue->value; }
    $fuelValue = is_scalar($fuelValue) ? (string) $fuelValue : '';
    $fuelRaw = strtolower(trim($fuelValue));

    $gearboxValue = $listing->transmission ?? '';
    if ($gearboxValue instanceof \BackedEnum) { $gearboxValue = $gearboxValue->value; }
    $gearboxValue = is_scalar($gearboxValue) ? (string) $gearboxValue : '';
    $gearboxRaw = strtolower(trim($gearboxValue));

    $fuelLabels = [
        'diesel' => 'Diesel', 'essence' => 'Essence', 'hybride' => 'Hybride',
        'electrique' => 'Electrique', 'gpl' => 'GPL', 'gaz' => 'Gaz',
    ];
    $gearboxLabels = [
        'automatic' => 'Automatique', 'manual' => 'Manuelle', 'manuel' => 'Manuelle',
        'manuelle' => 'Manuelle', 'semi-automatic' => 'Semi-auto', 'direct no gearbox' => 'Sans boite',
    ];
@endphp

<a href="{{ $detailsUrl }}"
   class="bg-[#1a1a1a] rounded-xl border border-[#2a2a2a] overflow-hidden hover:border-[#d4af37] hover:shadow-lg hover:shadow-[#d4af37]/5 hover:-translate-y-0.5 transition-all group block">

    <div class="relative aspect-[4/3] bg-[#222] overflow-hidden">
        @if($firstImage)
            <img src="{{ $firstImage }}"
                 alt="{{ $title }}"
                 referrerpolicy="no-referrer"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
            <div class="w-full h-full items-center justify-center text-gray-700" style="display:none;">
                <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/>
                </svg>
            </div>
        @else
            <div class="w-full h-full flex items-center justify-center text-gray-700">
                <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/>
                </svg>
            </div>
        @endif

        <div class="absolute top-2 left-2 flex gap-1.5">
            @if($isExpired)
                <span class="bg-gray-800 text-gray-300 text-xs font-bold px-2 py-0.5 rounded-full">Expiree</span>
            @endif
            @switch($listingType)
                @case('auction_open')
                    <span class="bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">Enchere ouverte</span>
                    @break
                @case('auction_blind')
                    <span class="bg-purple-700 text-white text-xs font-bold px-2 py-0.5 rounded-full">Enchere secrete</span>
                    @break
                @case('fixed_price')
                    <span class="bg-emerald-700 text-white text-xs font-bold px-2 py-0.5 rounded-full">Prix fixe</span>
                    @break
                @case('partner_stock')
                    <span class="bg-[#d4af37] text-black text-xs font-bold px-2 py-0.5 rounded-full">Stock</span>
                    @break
                @default
                    @if($isAuction)
                        <span class="bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">Enchere</span>
                    @endif
            @endswitch
        </div>

        @if($isAuction && $listing->auction_end_at)
            <div class="absolute bottom-2 left-2 right-2">
                <div class="bg-black/80 text-white text-xs px-2 py-1 rounded-lg flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 bg-red-400 rounded-full animate-pulse"></span>
                    <span>Fin {{ $listing->auction_end_at->diffForHumans() }}</span>
                </div>
            </div>
        @endif
    </div>

    <div class="p-4">
        <h3 class="font-semibold text-white text-sm leading-tight mb-2 line-clamp-2 group-hover:text-[#d4af37] transition-colors">
            {{ $title !== '' ? $title : 'Annonce eCarsTrade' }}
        </h3>

        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 mb-3">
            @if($listing->year)
                <span>{{ $listing->year }}</span>
            @endif
            @if($listing->mileage)
                <span>{{ number_format((int) $listing->mileage, 0, ',', ' ') }} km</span>
            @endif
            @if($listing->fuel)
                <span>{{ $fuelLabels[$fuelRaw] ?? $fuelValue }}</span>
            @endif
            @if($listing->transmission)
                <span>{{ $gearboxLabels[$gearboxRaw] ?? $gearboxValue }}</span>
            @endif
        </div>

        <div class="flex items-center justify-between mt-auto">
            <div>
                @if($priceVisible && $displayPrice !== null)
                    <div class="text-lg font-bold text-[#d4af37]">
                        {{ number_format($displayPrice, 0, ',', ' ') }} EUR
                    </div>
                @elseif($estimate && $estimate->estimated_price_min !== null && $estimate->estimated_price_max !== null)
                    <div class="text-sm text-[#d4af37] font-semibold">
                        {{ number_format((float) $estimate->estimated_price_min, 0, ',', ' ') }}
                        – {{ number_format((float) $estimate->estimated_price_max, 0, ',', ' ') }} EUR
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Prix estime</div>
                @elseif($isAuction)
                    <div class="text-sm text-gray-500 font-medium">Prix a estimer</div>
                    <div class="text-xs text-[#d4af37] mt-1">Ouvrir la fiche pour encherir.</div>
                @else
                    <div class="text-sm text-gray-600 italic">Prix sur demande</div>
                @endif
            </div>
        </div>
    </div>
</a>
