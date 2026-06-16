@extends('layouts.app')
@section('title', 'Admin – Dashboard')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')

    <h1 class="text-2xl font-bold mb-6">Pilotage MBPRESTIGE</h1>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Clients</p><p class="text-2xl font-bold">{{ $kpis['clients'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Utilisateurs total</p><p class="text-2xl font-bold">{{ $kpis['users_total'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Annonces publiées</p><p class="text-2xl font-bold">{{ $kpis['listings_published'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Annonces eCarsTrade publiées</p><p class="text-2xl font-bold">{{ $kpis['external_listings_published'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Enchères eCarsTrade actives</p><p class="text-2xl font-bold">{{ $kpis['external_live_auctions'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Vues annonces eCarsTrade</p><p class="text-2xl font-bold">{{ number_format($kpis['external_views_total'], 0, ',', ' ') }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Enchères déposées (site)</p><p class="text-2xl font-bold">{{ $kpis['external_bids_total'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Enchérisseurs uniques</p><p class="text-2xl font-bold">{{ $kpis['external_bidders_unique'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Réservations actives</p><p class="text-2xl font-bold">{{ $kpis['reservations_active'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Acomptes payés</p><p class="text-2xl font-bold">{{ $kpis['deposits_paid'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Offres actives</p><p class="text-2xl font-bold">{{ $kpis['bids_active'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Demandes clients</p><p class="text-2xl font-bold">{{ $kpis['client_requests'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Tickets support ouverts</p><p class="text-2xl font-bold">{{ $kpis['support_tickets_open'] }}</p></div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 font-semibold">Annonces les plus vues</div>
            <div class="divide-y divide-gray-100">
                @forelse($topViewedListings as $item)
                    <div class="px-4 py-3 text-sm flex items-center justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $item->title ?: trim(($item->make ?: '') . ' ' . ($item->model ?: '')) }}</div>
                            <div class="text-gray-500">{{ number_format((int) $item->views_count, 0, ',', ' ') }} vues</div>
                        </div>
                        <a href="{{ route('admin.external_listings.show', $item->id) }}" class="text-blue-700 hover:underline">Voir</a>
                    </div>
                @empty
                    <div class="px-4 py-8 text-sm text-gray-400">Aucune vue enregistrée.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 font-semibold">Annonces les plus enchéries</div>
            <div class="divide-y divide-gray-100">
                @forelse($topBidListings as $item)
                    <div class="px-4 py-3 text-sm flex items-center justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $item->title ?: trim(($item->make ?: '') . ' ' . ($item->model ?: '')) }}</div>
                            <div class="text-gray-500">{{ number_format((int) $item->bids_count, 0, ',', ' ') }} enchere(s)</div>
                        </div>
                        <a href="{{ route('admin.external_listings.show', $item->id) }}" class="text-blue-700 hover:underline">Voir</a>
                    </div>
                @empty
                    <div class="px-4 py-8 text-sm text-gray-400">Aucune enchère enregistrée.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 font-semibold">Marques tendance</div>
            <div class="divide-y divide-gray-100">
                @forelse($popularMakes as $row)
                    <div class="px-4 py-3 text-sm flex items-center justify-between">
                        <span>{{ $row->make }}</span>
                        <span class="font-semibold">{{ number_format((int) $row->total, 0, ',', ' ') }}</span>
                    </div>
                @empty
                    <div class="px-4 py-8 text-sm text-gray-400">Aucune donnée.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 font-semibold">Modèles tendance</div>
            <div class="divide-y divide-gray-100">
                @forelse($popularModels as $row)
                    <div class="px-4 py-3 text-sm flex items-center justify-between">
                        <span>{{ $row->model }}</span>
                        <span class="font-semibold">{{ number_format((int) $row->total, 0, ',', ' ') }}</span>
                    </div>
                @empty
                    <div class="px-4 py-8 text-sm text-gray-400">Aucune donnée.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-gray-100 font-semibold">Dernieres encheres clients (annonces eCarsTrade)</div>
        <div class="divide-y divide-gray-100">
            @forelse($latestExternalBids as $bid)
                <div class="px-4 py-3 text-sm flex items-center justify-between gap-3">
                    <div>
                        <div class="font-medium">
                            {{ $bid->listing?->title ?: trim(($bid->listing?->make ?: '') . ' ' . ($bid->listing?->model ?: '')) }}
                        </div>
                        <div class="text-gray-500">
                            {{ $bid->user?->name ?: 'Client' }} ({{ $bid->user?->email ?: 'email indisponible' }})
                            · {{ number_format((float) $bid->amount, 0, ',', ' ') }} {{ $bid->currency }}
                        </div>
                    </div>
                    <a href="{{ $bid->listing ? route('admin.external_listings.show', $bid->listing->id) : '#' }}" class="text-blue-700 hover:underline">Voir</a>
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-gray-400">Aucune enchere client pour le moment.</div>
            @endforelse
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 font-semibold">Dernières réservations</div>
            <div class="divide-y divide-gray-100">
                @forelse($latestPurchases as $purchase)
                    <div class="px-4 py-3 text-sm flex items-center justify-between">
                        <div>
                            <div class="font-medium">{{ $purchase->listing?->title ?? 'Annonce #' . $purchase->listing_id }}</div>
                            <div class="text-gray-500">{{ $purchase->user?->email ?? 'N/A' }}</div>
                        </div>
                        <a href="{{ route('admin.purchases.show', $purchase) }}" class="text-blue-700 hover:underline">Voir</a>
                    </div>
                @empty
                    <div class="px-4 py-8 text-sm text-gray-400">Aucune réservation.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 font-semibold">Derniers paiements</div>
            <div class="divide-y divide-gray-100">
                @forelse($latestPayments as $payment)
                    <div class="px-4 py-3 text-sm flex items-center justify-between">
                        <div>
                            <div class="font-medium">{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $payment->currency }}</div>
                            <div class="text-gray-500">{{ $payment->user?->email ?? 'N/A' }} · {{ $payment->status->value }}</div>
                        </div>
                        <a href="{{ route('admin.payments.show', $payment) }}" class="text-blue-700 hover:underline">Voir</a>
                    </div>
                @empty
                    <div class="px-4 py-8 text-sm text-gray-400">Aucun paiement.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
