@extends('layouts.app')
@section('title', 'Admin – Dashboard')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')

    <h1 class="text-2xl font-bold mb-6">Pilotage MBPRESTIGE</h1>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Clients</p><p class="text-2xl font-bold">{{ $kpis['clients'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Annonces publiées</p><p class="text-2xl font-bold">{{ $kpis['listings_published'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Réservations actives</p><p class="text-2xl font-bold">{{ $kpis['reservations_active'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Acomptes payés</p><p class="text-2xl font-bold">{{ $kpis['deposits_paid'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Offres actives</p><p class="text-2xl font-bold">{{ $kpis['bids_active'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Demandes clients</p><p class="text-2xl font-bold">{{ $kpis['client_requests'] }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Tickets support ouverts</p><p class="text-2xl font-bold">{{ $kpis['support_tickets_open'] }}</p></div>
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
