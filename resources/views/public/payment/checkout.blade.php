@extends('layouts.app')

@section('title', 'Paiement acompte – MBPRESTIGE')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Paiement de l’acompte</h1>
    <p class="text-sm text-gray-500 mb-8">
        Réservation de <strong>{{ $listing->title }}</strong>. Vous payez uniquement un acompte maintenant.
    </p>
    @if(isset($purchase) && $purchase->expires_at)
        <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-3">
            Réservation valable jusqu’au <strong>{{ $purchase->expires_at->format('d/m/Y H:i') }}</strong>.
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">Montant véhicule</span>
            <span class="font-semibold">{{ number_format($summary['vehicle_amount'], 0, ',', ' ') }} €</span>
        </div>
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">Commission</span>
            <span class="font-semibold">{{ number_format($summary['commission'], 0, ',', ' ') }} €</span>
        </div>
        <div class="flex items-center justify-between text-sm border-t border-gray-100 pt-4">
            <span class="text-gray-700 font-medium">Total dossier</span>
            <span class="text-lg font-bold">{{ number_format($summary['total'], 0, ',', ' ') }} €</span>
        </div>
        <div class="flex items-center justify-between text-sm bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3">
            <span class="text-emerald-800 font-semibold">Acompte à régler maintenant ({{ (int) round($summary['deposit_rate'] * 100) }}%)</span>
            <span class="text-emerald-900 text-lg font-bold">{{ number_format($summary['deposit_now'], 0, ',', ' ') }} €</span>
        </div>
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">Reste à payer (traité ensuite)</span>
            <span class="font-semibold">{{ number_format($summary['remaining_after_deposit'], 0, ',', ' ') }} €</span>
        </div>
    </div>

    <form action="{{ route('app.payment.checkout', $listing) }}" method="POST" class="mt-6">
        @csrf
        <button type="submit"
                class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-3 rounded-xl">
            Payer l’acompte maintenant
        </button>
    </form>
</div>
@endsection
