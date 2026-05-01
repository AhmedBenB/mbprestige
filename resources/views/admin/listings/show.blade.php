@extends('layouts.app')
@section('title', 'Admin – Détail annonce')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')

    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
            <div>
                <h1 class="text-2xl font-bold">{{ $listing->title }}</h1>
                <p class="text-sm text-gray-500">Slug: {{ $listing->slug }} · ID {{ $listing->id }}</p>
            </div>
            <a href="{{ route('admin.listings.index') }}" class="text-sm text-blue-700 hover:underline">Retour liste</a>
        </div>

        <div class="grid md:grid-cols-2 gap-6 text-sm">
            <div class="space-y-2">
                <p><span class="text-gray-500">Type:</span> {{ $listing->listing_type->value }}</p>
                <p><span class="text-gray-500">Statut publication:</span> {{ $listing->publication_status->value }}</p>
                <p><span class="text-gray-500">Statut enchère:</span> {{ $listing->auction_status?->value ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Prix fixe:</span> {{ $listing->buy_now_price ? number_format((float) $listing->buy_now_price, 0, ',', ' ') . ' €' : 'N/A' }}</p>
                <p><span class="text-gray-500">Current bid:</span> {{ $listing->current_bid ? number_format((float) $listing->current_bid, 0, ',', ' ') . ' €' : 'N/A' }}</p>
            </div>
            <div class="space-y-2">
                <p><span class="text-gray-500">Véhicule:</span> {{ $listing->vehicle?->full_name ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Source:</span> {{ $listing->source?->name ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Publié le:</span> {{ $listing->published_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Début:</span> {{ $listing->starts_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Fin:</span> {{ $listing->ends_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
