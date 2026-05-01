@extends('layouts.app')

@section('title', 'Paiement annulé – MBPRESTIGE')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="bg-white border border-amber-200 rounded-2xl p-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Paiement annulé</h1>
        <p class="text-gray-600 mb-6">
            Aucun débit n’a été validé pour <strong>{{ $listing->title }}</strong>.
            Votre réservation reste temporairement active jusqu’à expiration.
        </p>
        @if(isset($purchase) && $purchase && $purchase->expires_at)
            <p class="text-xs text-gray-500 mb-6">
                Réservation #{{ $purchase->id }} valable jusqu’au {{ $purchase->expires_at->format('d/m/Y H:i') }}.
            </p>
        @endif

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('app.payment.show', $listing) }}"
               class="bg-blue-700 hover:bg-blue-800 text-white font-semibold px-5 py-2.5 rounded-lg">
                Réessayer le paiement
            </a>
            <a href="{{ route('vehicles.show', $listing) }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold px-5 py-2.5 rounded-lg">
                Retour au véhicule
            </a>
        </div>
    </div>
</div>
@endsection
