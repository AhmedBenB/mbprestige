@extends('layouts.app')
@section('title', 'Admin – Détail paiement')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')

    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold">Paiement #{{ $payment->id }}</h1>
                <p class="text-sm text-gray-500">{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $payment->currency }} · {{ $payment->status->value }}</p>
            </div>
            <a href="{{ route('admin.payments.index') }}" class="text-blue-700 hover:underline text-sm">Retour liste</a>
        </div>

        <div class="grid md:grid-cols-2 gap-6 text-sm mb-6">
            <div class="space-y-2">
                <p><span class="text-gray-500">Client:</span> {{ $payment->user?->name ?? 'N/A' }} ({{ $payment->user?->email ?? 'N/A' }})</p>
                <p><span class="text-gray-500">Téléphone:</span> {{ $payment->user?->phone ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Type:</span> {{ $payment->type->value }}</p>
                <p><span class="text-gray-500">Provider:</span> {{ $payment->provider }}</p>
            </div>
            <div class="space-y-2">
                <p><span class="text-gray-500">Session Stripe:</span> {{ $payment->provider_session_id ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Intent Stripe:</span> {{ $payment->provider_payment_intent_id ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Payé le:</span> {{ $payment->paid_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Réservation liée:</span>
                    @if($payment->purchase)
                        <a href="{{ route('admin.purchases.show', $payment->purchase) }}" class="text-blue-700 hover:underline">#{{ $payment->purchase->id }}</a>
                    @else
                        N/A
                    @endif
                </p>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-6">
            <h2 class="font-semibold mb-3">Actions Admin</h2>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.payments.mark_paid', $payment) }}">
                    @csrf
                    <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
                        Marquer payé
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.payments.mark_failed', $payment) }}">
                    @csrf
                    <button class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
                        Marquer échec
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.payments.mark_refunded', $payment) }}">
                    @csrf
                    <button class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-lg"
                        onclick="return confirm('Marquer ce paiement comme remboursé ?')">
                        Marquer remboursé
                    </button>
                </form>
            </div>
        </div>

        @if($payment->metadata)
            <div class="mt-8">
                <h2 class="font-semibold mb-3">Metadata</h2>
                <pre class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs overflow-x-auto">{{ json_encode($payment->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @endif
    </div>
</div>
@endsection
