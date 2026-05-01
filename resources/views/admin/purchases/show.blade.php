@extends('layouts.app')
@section('title', 'Admin – Détail vente')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')

    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold">Réservation #{{ $purchase->id }}</h1>
                <p class="text-sm text-gray-500">{{ $purchase->listing?->title ?? 'Annonce N/A' }}</p>
            </div>
            <a href="{{ route('admin.purchases.index') }}" class="text-blue-700 hover:underline text-sm">Retour liste</a>
        </div>

        <div class="grid md:grid-cols-2 gap-6 text-sm mb-6">
            <div class="space-y-2">
                <p><span class="text-gray-500">Client:</span> {{ $purchase->user?->name ?? 'N/A' }} ({{ $purchase->user?->email ?? 'N/A' }})</p>
                <p><span class="text-gray-500">Téléphone:</span> {{ $purchase->user?->phone ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Entreprise:</span> {{ $purchase->user?->organization?->name ?? 'N/A' }}</p>
            </div>
            <div class="space-y-2">
                <p><span class="text-gray-500">Statut:</span> {{ $purchase->status->value }}</p>
                <p><span class="text-gray-500">Réservé le:</span> {{ $purchase->reserved_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Acompte payé le:</span> {{ $purchase->deposit_paid_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Expire le:</span> {{ $purchase->expires_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-6">
            <h2 class="font-semibold mb-3">Actions Admin</h2>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.purchases.mark_deposit_paid', $purchase) }}">
                    @csrf
                    <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
                        Marquer acompte payé
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.purchases.mark_completed', $purchase) }}">
                    @csrf
                    <button class="bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">
                        Marquer solde payé
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.purchases.cancel', $purchase) }}">
                    @csrf
                    <button class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-lg"
                        onclick="return confirm('Annuler cette réservation ?')">
                        Annuler réservation
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-8">
            <h2 class="font-semibold mb-3">Paiements liés</h2>
            <div class="space-y-2 text-sm">
                @forelse($purchase->payments as $payment)
                    <div class="border border-gray-200 rounded-lg px-3 py-2 flex items-center justify-between">
                        <div>#{{ $payment->id }} · {{ $payment->type->value }} · {{ $payment->status->value }}</div>
                        <a href="{{ route('admin.payments.show', $payment) }}" class="text-blue-700 hover:underline">Voir</a>
                    </div>
                @empty
                    <p class="text-gray-400">Aucun paiement lié.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
