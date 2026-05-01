@extends('layouts.app')

@section('title', 'Acompte confirmé – MBPRESTIGE')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="bg-white border border-emerald-200 rounded-2xl p-8 text-center">
        <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl">
            ✓
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Acompte confirmé</h1>
        <p class="text-gray-600 mb-6">
            Votre acompte pour <strong>{{ $listing->title }}</strong> est bien enregistré.
            Le solde sera traité dans l’étape suivante avec notre équipe.
        </p>
        @if(isset($summary))
            <div class="mb-6 text-sm text-left bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <span>Acompte réglé</span>
                    <strong>{{ number_format($summary['deposit_now'], 0, ',', ' ') }} €</strong>
                </div>
                <div class="flex items-center justify-between">
                    <span>Solde restant</span>
                    <strong>{{ number_format($summary['remaining_after_deposit'], 0, ',', ' ') }} €</strong>
                </div>
            </div>
        @endif
        @if(isset($purchase) && $purchase)
            <p class="text-xs text-gray-500 mb-6">
                Réservation #{{ $purchase->id }} · Statut : {{ $purchase->status->value }}
            </p>
        @endif

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('app.dashboard') }}"
               class="bg-blue-700 hover:bg-blue-800 text-white font-semibold px-5 py-2.5 rounded-lg">
                Retour à mon espace
            </a>
            <a href="{{ route('vehicles.show', $listing) }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold px-5 py-2.5 rounded-lg">
                Voir le véhicule
            </a>
        </div>
    </div>
</div>
@endsection
