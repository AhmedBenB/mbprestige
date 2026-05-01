@extends('layouts.app')
@section('title', 'Admin – Annonces')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Gestion des annonces</h1>
        <a href="{{ route('admin.dashboard') }}"
           class="bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-800">
            Retour dashboard
        </a>
    </div>

    {{-- Filtres rapides --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Statut</label>
            <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Tous</option>
                @foreach(['draft','imported','enriched','ready_for_review','approved','published','paused','archived','rejected'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Type</label>
            <select name="type" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Tous</option>
                <option value="auction_open"  @selected(request('type')==='auction_open')>Enchère ouverte</option>
                <option value="auction_blind" @selected(request('type')==='auction_blind')>Enchère blind</option>
                <option value="fixed_price"   @selected(request('type')==='fixed_price')>Prix fixe</option>
                <option value="partner_stock" @selected(request('type')==='partner_stock')>Stock partenaire</option>
            </select>
        </div>
        <div class="flex-1 min-w-48">
            <label class="text-xs text-gray-500 block mb-1">Recherche</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Titre, ID…"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-800">
            Filtrer
        </button>
        <a href="{{ route('admin.listings.index') }}" class="border border-gray-300 text-sm text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50">
            Reset
        </a>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Véhicule</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Statut pub.</th>
                        <th class="px-4 py-3 text-left">Enchère</th>
                        <th class="px-4 py-3 text-right">Prix</th>
                        <th class="px-4 py-3 text-center">Offres</th>
                        <th class="px-4 py-3 text-left">Source</th>
                        <th class="px-4 py-3 text-left">Sync</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($listings as $listing)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-400 font-mono text-xs">{{ $listing->id }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                @if($listing->coverImage)
                                    <img src="{{ $listing->coverImage->url() }}" alt=""
                                         class="w-10 h-8 object-cover rounded flex-shrink-0">
                                @else
                                    <div class="w-10 h-8 bg-gray-100 rounded flex-shrink-0"></div>
                                @endif
                                <div>
                                    <div class="font-medium text-gray-900 max-w-48 truncate">{{ $listing->title }}</div>
                                    <div class="text-xs text-gray-400">{{ $listing->vehicle->make }} {{ $listing->vehicle->model }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">
                                {{ $listing->listing_type->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $statusColors = [
                                    'published' => 'bg-green-100 text-green-700',
                                    'approved'  => 'bg-blue-100 text-blue-700',
                                    'ready_for_review' => 'bg-yellow-100 text-yellow-700',
                                    'paused'    => 'bg-orange-100 text-orange-700',
                                    'archived'  => 'bg-gray-100 text-gray-500',
                                    'rejected'  => 'bg-red-100 text-red-700',
                                ];
                                $color = $statusColors[$listing->publication_status->value] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <span class="text-xs px-1.5 py-0.5 rounded {{ $color }}">
                                {{ $listing->publication_status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $listing->auction_status ? $listing->auction_status->label() : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-gray-900">
                            {{ $listing->current_bid ?? $listing->buy_now_price ?? $listing->starting_price
                                ? number_format(($listing->current_bid ?? $listing->buy_now_price ?? $listing->starting_price), 0, ',', ' ').' €'
                                : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $listing->bid_count ?: '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $listing->source?->name ?? 'Manuel' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400">
                            {{ $listing->last_source_sync_at?->diffForHumans() ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('admin.listings.show', $listing) }}"
                                   class="p-1.5 text-gray-400 hover:text-blue-700 rounded" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                @if(in_array($listing->publication_status->value, ['imported','enriched','ready_for_review']))
                                <form action="{{ route('admin.listings.approve', $listing) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-blue-700 rounded" title="Approuver">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </form>
                                @endif
                                @if($listing->publication_status->value === 'approved')
                                <form action="{{ route('admin.listings.publish', $listing) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-green-700 rounded" title="Publier">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    </button>
                                </form>
                                @endif
                                @if($listing->publication_status->value === 'published')
                                <form action="{{ route('admin.listings.pause', $listing) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-orange-600 rounded" title="Pause">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                </form>
                                @endif
                                <form action="{{ route('admin.listings.archive', $listing) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 rounded" title="Archiver"
                                            onclick="return confirm('Archiver cette annonce ?')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-gray-400 text-sm">
                            Aucune annonce trouvée.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($listings->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $listings->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
