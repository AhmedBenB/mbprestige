@extends('layouts.app')
@section('title', 'Admin - Detail annonce eCarsTrade')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')

    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ $listing->title ?: trim(($listing->make ?: '') . ' ' . ($listing->model ?: '')) }}</h1>
            <p class="text-sm text-gray-500">External ID: {{ $listing->external_id }} · Statut: {{ $listing->status }}</p>
        </div>
        <a href="{{ route('admin.external_listings.index') }}" class="text-sm text-blue-700 hover:underline">Retour liste</a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl p-5">
            <h2 class="font-semibold mb-3">Resume annonce</h2>
            <div class="grid sm:grid-cols-2 gap-y-2 gap-x-6 text-sm">
                <div><span class="text-gray-500">Type:</span> {{ $listing->listing_type }}</div>
                <div><span class="text-gray-500">Source status:</span> {{ $listing->source_status ?: 'N/A' }}</div>
                <div><span class="text-gray-500">Marque:</span> {{ $listing->make ?: 'N/A' }}</div>
                <div><span class="text-gray-500">Modele:</span> {{ $listing->model ?: 'N/A' }}</div>
                <div><span class="text-gray-500">Annee:</span> {{ $listing->year ?: 'N/A' }}</div>
                <div><span class="text-gray-500">Km:</span> {{ $listing->mileage ? number_format((int) $listing->mileage, 0, ',', ' ') : 'N/A' }}</div>
                <div><span class="text-gray-500">Carburant:</span> {{ $listing->fuel ?: 'N/A' }}</div>
                <div><span class="text-gray-500">Boite:</span> {{ $listing->transmission ?: 'N/A' }}</div>
                <div><span class="text-gray-500">Fin enchere:</span> {{ $listing->auction_end_at?->format('d/m/Y H:i') ?? 'N/A' }}</div>
                <div><span class="text-gray-500">Vues:</span> {{ number_format((int) $listing->views_count, 0, ',', ' ') }}</div>
                <div><span class="text-gray-500">Prix visible:</span> {{ $listing->price_visible ? 'Oui' : 'Non' }}</div>
                <div><span class="text-gray-500">Prix:</span>
                    @if($listing->price_visible && $listing->price_amount !== null)
                        {{ number_format((float) $listing->price_amount, 0, ',', ' ') }} {{ $listing->currency }}
                    @elseif($listing->latestPriceEstimate)
                        Estime: {{ number_format((float) $listing->latestPriceEstimate->estimated_price_min, 0, ',', ' ') }}
                        - {{ number_format((float) $listing->latestPriceEstimate->estimated_price_max, 0, ',', ' ') }} {{ $listing->currency }}
                    @else
                        N/A
                    @endif
                </div>
            </div>

            @if($listing->listing_url)
                <div class="mt-4">
                    <a href="{{ $listing->listing_url }}" target="_blank" rel="noopener" class="text-sm text-blue-700 hover:underline">Voir source eCarsTrade</a>
                </div>
            @endif
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h2 class="font-semibold mb-3">Monitoring</h2>
            <ul class="space-y-2 text-sm">
                <li><span class="text-gray-500">Source:</span> {{ $listing->source?->name ?: 'N/A' }}</li>
                <li><span class="text-gray-500">Publiée:</span> {{ $listing->published_at?->format('d/m/Y H:i') ?: 'N/A' }}</li>
                <li><span class="text-gray-500">Derniere synchro:</span> {{ $listing->last_seen_at?->format('d/m/Y H:i') ?: 'N/A' }}</li>
                <li><span class="text-gray-500">Mise a jour:</span> {{ $listing->updated_at?->format('d/m/Y H:i') ?: 'N/A' }}</li>
                <li><span class="text-gray-500">Documents:</span> {{ $listing->documents->count() }}</li>
                <li><span class="text-gray-500">Encheres:</span> {{ $listing->bids->count() }}</li>
                <li><span class="text-gray-500">Paiements lies:</span> {{ $payments->count() }}</li>
            </ul>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-gray-100 font-semibold">Qui a encheri ?</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase">
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Client</th>
                        <th class="px-4 py-3 text-left">Organisation</th>
                        <th class="px-4 py-3 text-right">Montant</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($listing->bids->sortByDesc('placed_at') as $bid)
                        <tr>
                            <td class="px-4 py-3">{{ $bid->placed_at?->format('d/m/Y H:i') ?: $bid->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $bid->user?->name ?: 'N/A' }}<br><span class="text-xs text-gray-500">{{ $bid->user?->email ?: 'N/A' }}</span></td>
                            <td class="px-4 py-3">{{ $bid->organization?->name ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $bid->amount, 0, ',', ' ') }} {{ $bid->currency }}</td>
                            <td class="px-4 py-3">{{ $bid->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucune enchere sur cette annonce.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 font-semibold">Qui a paye ? (paiements relies a cette annonce)</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase">
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Client</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-right">Montant</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Purchase ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($payments as $payment)
                        <tr>
                            <td class="px-4 py-3">{{ $payment->paid_at?->format('d/m/Y H:i') ?: $payment->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $payment->user?->name ?: 'N/A' }}<br><span class="text-xs text-gray-500">{{ $payment->user?->email ?: 'N/A' }}</span></td>
                            <td class="px-4 py-3">{{ data_get($payment, 'type.value', $payment->type) ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $payment->currency }}</td>
                            <td class="px-4 py-3">{{ data_get($payment, 'status.value', $payment->status) ?: 'N/A' }}</td>
                            <td class="px-4 py-3">{{ $payment->purchase_id ?: 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aucun paiement encore lie a cette annonce.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

