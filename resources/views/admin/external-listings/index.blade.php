@extends('layouts.app')
@section('title', 'Admin - Annonces eCarsTrade')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold">Annonces eCarsTrade</h1>
            <p class="text-sm text-gray-500">Statut expire + purge automatique ({{ $kpis['retention_days'] }} jours)</p>
        </div>
        <form method="POST" action="{{ route('admin.external_listings.lifecycle') }}" class="bg-white border border-gray-200 rounded-xl p-3 flex items-end gap-3">
            @csrf
            <div>
                <label for="retention_days" class="text-xs text-gray-500 block mb-1">Retention (jours)</label>
                <input id="retention_days" type="number" name="retention_days" min="1" max="90" value="{{ $kpis['retention_days'] }}"
                    class="w-24 border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-800">
                Lancer expire + purge
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('lifecycle_output'))
        <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-3">
            <p class="text-xs font-semibold text-blue-700 mb-2">Sortie commande lifecycle</p>
            <pre class="text-xs text-blue-900 whitespace-pre-wrap">{{ session('lifecycle_output') }}</pre>
        </div>
    @endif

    <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Publiees</p><p class="text-2xl font-bold">{{ number_format($kpis['published'], 0, ',', ' ') }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Expirees</p><p class="text-2xl font-bold">{{ number_format($kpis['expired'], 0, ',', ' ') }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">A purger maintenant</p><p class="text-2xl font-bold">{{ number_format($kpis['purgeable'], 0, ',', ' ') }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Encheres clients</p><p class="text-2xl font-bold">{{ number_format($kpis['bids_total'], 0, ',', ' ') }}</p></div>
        <div class="bg-white border border-gray-200 rounded-xl p-4"><p class="text-xs text-gray-500">Sync auto</p><p class="text-2xl font-bold">/{{ $kpis['sync_every_minutes'] }} min</p></div>
    </div>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Statut</label>
            <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                @foreach(['published','expired','draft','ready_for_review','do_not_publish'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Type</label>
            <select name="type" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                @foreach(['auction_open','auction_blind','fixed_price','stock','unknown'] as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Avec encheres</label>
            <select name="has_bids" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                <option value="yes" @selected(request('has_bids') === 'yes')>Oui</option>
            </select>
        </div>
        <div class="flex-1 min-w-52">
            <label class="text-xs text-gray-500 block mb-1">Recherche</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Titre, external_id, marque, modele"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <button type="submit" class="bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-800">Filtrer</button>
        <a href="{{ route('admin.external_listings.index') }}" class="border border-gray-300 text-sm text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50">Reset</a>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Annonce</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Fin enchere</th>
                        <th class="px-4 py-3 text-right">Prix</th>
                        <th class="px-4 py-3 text-center">Vues</th>
                        <th class="px-4 py-3 text-center">Encheres</th>
                        <th class="px-4 py-3 text-left">Maj</th>
                        <th class="px-4 py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($listings as $listing)
                        @php
                            $badge = match ($listing->status) {
                                'published' => 'bg-green-100 text-green-700',
                                'expired' => 'bg-amber-100 text-amber-700',
                                'draft' => 'bg-gray-100 text-gray-700',
                                default => 'bg-blue-100 text-blue-700',
                            };
                            $displayPrice = $listing->price_visible && $listing->price_amount !== null
                                ? number_format((float) $listing->price_amount, 0, ',', ' ') . ' ' . $listing->currency
                                : (($listing->latestPriceEstimate && $listing->latestPriceEstimate->estimated_price_min && $listing->latestPriceEstimate->estimated_price_max)
                                    ? 'Estime: ' . number_format((float) $listing->latestPriceEstimate->estimated_price_min, 0, ',', ' ') . ' - ' . number_format((float) $listing->latestPriceEstimate->estimated_price_max, 0, ',', ' ') . ' ' . $listing->currency
                                    : 'N/A');
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ $listing->id }}<br>#{{ $listing->external_id }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $listing->title ?: trim(($listing->make ?: '') . ' ' . ($listing->model ?: '')) }}</div>
                                <div class="text-xs text-gray-500">{{ $listing->make ?: 'N/A' }} {{ $listing->model ?: '' }} @if($listing->year) - {{ $listing->year }} @endif</div>
                            </td>
                            <td class="px-4 py-3">{{ $listing->listing_type }}</td>
                            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full {{ $badge }}">{{ $listing->status }}</span></td>
                            <td class="px-4 py-3 text-xs text-gray-600">{{ $listing->auction_end_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ $displayPrice }}</td>
                            <td class="px-4 py-3 text-center">{{ number_format((int) $listing->views_count, 0, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-center">{{ number_format((int) $listing->bids_count, 0, ',', ' ') }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $listing->updated_at?->diffForHumans() ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('admin.external_listings.show', $listing) }}" class="text-blue-700 hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-400">Aucune annonce eCarsTrade.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($listings->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $listings->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

