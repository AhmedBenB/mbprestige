@extends('layouts.app')
@section('title', 'Mon espace – MBPRESTIGE')

@section('content')
@php
    $user = auth()->user();
    $org  = $user->organization;
    $activeBids = \App\Models\Bid::where('user_id', $user->id)
        ->whereIn('status', ['pending','leading','won_pending_validation'])
        ->count();
    $favCount   = \App\Models\Favorite::where('user_id', $user->id)->count();
    $recentBids = \App\Models\Bid::where('user_id', $user->id)
        ->with(['listing.vehicle','listing.coverImage'])
        ->latest('placed_at')
        ->take(5)
        ->get();
    $favorites  = \App\Models\Favorite::where('user_id', $user->id)
        ->with(['listing.vehicle','listing.coverImage','listing.auction'])
        ->latest()
        ->take(6)
        ->get();
    $supportCount = \App\Models\SupportTicket::where('user_id', $user->id)->count();
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- En-tête --}}
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                Bonjour, {{ $user->first_name ?? $user->name }} 👋
            </h1>
            @if($org)
            <p class="text-gray-500 text-sm mt-1">
                {{ $org->name }} ·
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold
                    {{ $org->user_tier === 'golden' ? 'bg-yellow-100 text-yellow-700' :
                       ($org->user_tier === 'silver' ? 'bg-gray-100 text-gray-600' : 'bg-blue-50 text-blue-600') }}">
                    {{ ucfirst($org->user_tier) }}
                </span>
            </p>
            @endif
        </div>
        <a href="{{ route('catalog.index') }}"
           class="bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-800">
            Rechercher des véhicules
        </a>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach([
            ['label' => 'Offres actives',  'value' => $activeBids, 'color' => 'blue',   'href' => route('app.bids.index'),      'icon' => '📋'],
            ['label' => 'Favoris',         'value' => $favCount,   'color' => 'red',    'href' => route('app.favorites.index'), 'icon' => '❤️'],
            ['label' => 'Limite offres',   'value' => ($org?->maxActiveBids() ?? 5).' max', 'color' => 'gray', 'href' => null, 'icon' => '📊'],
            ['label' => 'Alertes',         'value' => \App\Models\SavedSearch::where('user_id',$user->id)->count(), 'color' => 'emerald', 'href' => route('app.alerts.index'), 'icon' => '🔔'],
            ['label' => 'Tickets support', 'value' => $supportCount, 'color' => 'indigo', 'href' => route('app.support.index'), 'icon' => '💬'],
        ] as $kpi)
        <div class="bg-white rounded-xl border border-gray-200 p-5 {{ $kpi['href'] ? 'hover:shadow-md transition-shadow' : '' }}">
            @if($kpi['href'])
                <a href="{{ $kpi['href'] }}" class="block">
            @endif
                <div class="text-2xl mb-1">{{ $kpi['icon'] }}</div>
                <div class="text-2xl font-bold text-gray-900">{{ $kpi['value'] }}</div>
                <div class="text-sm text-gray-500 mt-0.5">{{ $kpi['label'] }}</div>
            @if($kpi['href'])
                </a>
            @endif
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        {{-- Mes offres récentes --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="font-semibold text-gray-900">Mes offres récentes</h2>
                <a href="{{ route('app.bids.index') }}" class="text-sm text-blue-700 hover:underline">Voir tout</a>
            </div>
            @if($recentBids->isEmpty())
                <div class="px-6 py-10 text-center text-gray-400 text-sm">
                    Vous n'avez pas encore d'offres.
                    <br>
                    <a href="{{ route('catalog.auctions') }}" class="text-blue-700 hover:underline mt-1 inline-block">Voir les enchères →</a>
                </div>
            @else
                <div class="divide-y divide-gray-50">
                    @foreach($recentBids as $bid)
                    <div class="px-6 py-4 flex items-center gap-4">
                        @if($bid->listing->coverImage)
                            <img src="{{ $bid->listing->coverImage->url() }}" alt=""
                                 class="w-14 h-10 object-cover rounded flex-shrink-0">
                        @else
                            <div class="w-14 h-10 bg-gray-100 rounded flex-shrink-0"></div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-sm text-gray-900 truncate">
                                {{ $bid->listing->title }}
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                {{ $bid->placed_at?->format('d/m/Y à H:i') }}
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-bold text-gray-900 text-sm">
                                {{ number_format($bid->amount, 0, ',', ' ') }} €
                            </div>
                            @php
                                $badgeClass = match($bid->status->value) {
                                    'leading'  => 'bg-green-100 text-green-700',
                                    'outbid'   => 'bg-red-100 text-red-700',
                                    'accepted' => 'bg-emerald-100 text-emerald-700',
                                    'rejected', 'cancelled' => 'bg-gray-100 text-gray-500',
                                    default    => 'bg-blue-100 text-blue-700',
                                };
                            @endphp
                            <span class="text-xs font-medium px-1.5 py-0.5 rounded-full {{ $badgeClass }}">
                                {{ $bid->status->label() }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Mes favoris --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="font-semibold text-gray-900">Mes favoris</h2>
                <a href="{{ route('app.favorites.index') }}" class="text-sm text-blue-700 hover:underline">Voir tout</a>
            </div>
            @if($favorites->isEmpty())
                <div class="px-6 py-10 text-center text-gray-400 text-sm">
                    Aucun favori sauvegardé.
                    <br>
                    <a href="{{ route('catalog.index') }}" class="text-blue-700 hover:underline mt-1 inline-block">Parcourir le catalogue →</a>
                </div>
            @else
                <div class="grid grid-cols-3 gap-3 p-4">
                    @foreach($favorites as $fav)
                    <a href="{{ route('vehicles.show', $fav->listing) }}"
                       class="group block rounded-lg overflow-hidden border border-gray-100 hover:border-blue-300 transition-colors">
                        @if($fav->listing->coverImage)
                            <img src="{{ $fav->listing->coverImage->url() }}" alt="{{ $fav->listing->title }}"
                                 class="w-full h-20 object-cover group-hover:scale-105 transition-transform duration-200">
                        @else
                            <div class="w-full h-20 bg-gray-100"></div>
                        @endif
                        <div class="p-2">
                            <div class="text-xs font-medium text-gray-700 leading-tight truncate">
                                {{ $fav->listing->vehicle->make }} {{ $fav->listing->vehicle->model }}
                            </div>
                            @php
                                $p = $fav->listing->current_bid ?? $fav->listing->buy_now_price;
                            @endphp
                            @if($p)
                            <div class="text-xs font-bold text-gray-900 mt-0.5">
                                {{ number_format($p, 0, ',', ' ') }} €
                            </div>
                            @endif
                        </div>
                    </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Liens rapides --}}
    <div class="mt-8 grid grid-cols-2 sm:grid-cols-5 gap-3">
        @foreach([
            ['href' => route('catalog.auctions'),    'icon' => '⏱️', 'label' => 'Enchères en cours'],
            ['href' => route('catalog.fixed_prices'), 'icon' => '🏷️', 'label' => 'Prix fixes'],
            ['href' => route('app.alerts.index'),    'icon' => '🔔', 'label' => 'Mes alertes'],
            ['href' => route('app.profile.show'),    'icon' => '👤', 'label' => 'Mon profil'],
            ['href' => route('app.support.index'),   'icon' => '💬', 'label' => 'Support'],
        ] as $link)
        <a href="{{ $link['href'] }}"
           class="bg-white border border-gray-200 rounded-xl p-4 text-center hover:shadow-md hover:border-blue-300 transition-all">
            <div class="text-2xl mb-1">{{ $link['icon'] }}</div>
            <div class="text-sm font-medium text-gray-700">{{ $link['label'] }}</div>
        </a>
        @endforeach
    </div>

</div>
@endsection