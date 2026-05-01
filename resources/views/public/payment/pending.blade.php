@extends('layouts.app')

@section('title', 'Paiement en attente – MBPRESTIGE')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="bg-white border border-blue-200 rounded-2xl p-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Paiement en attente de confirmation</h1>
        <p class="text-gray-600 mb-6">
            Nous attendons la confirmation Stripe pour <strong>{{ $listing->title }}</strong>.
            Cette page se mettra à jour dès réception du webhook.
        </p>
        @if(isset($purchase) && $purchase)
            <p class="text-xs text-gray-500 mb-2">
                Réservation #{{ $purchase->id }} · Statut : {{ $purchase->status->value }}
            </p>
        @endif
        @if(isset($payment) && $payment)
            <p class="text-xs text-gray-500 mb-6">
                Paiement #{{ $payment->id }} · Statut : {{ $payment->status->value }}
            </p>
        @endif

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('app.payment.show', $listing) }}"
               class="bg-blue-700 hover:bg-blue-800 text-white font-semibold px-5 py-2.5 rounded-lg">
                Voir la page paiement
            </a>
            <a href="{{ route('app.dashboard') }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold px-5 py-2.5 rounded-lg">
                Retour à mon espace
            </a>
        </div>
    </div>
</div>
@endsection
