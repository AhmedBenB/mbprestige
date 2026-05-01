@extends('layouts.app')
@section('title', 'Mes offres – MBPRESTIGE')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Mes offres</h1>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        @forelse($bids as $bid)
            <div class="px-5 py-4 border-b border-gray-100 last:border-0 flex items-center justify-between gap-4">
                <div>
                    <div class="font-semibold text-gray-900">{{ $bid->listing->title ?? 'Annonce supprimée' }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $bid->placed_at?->format('d/m/Y H:i') }} · {{ $bid->status->label() }}
                    </div>
                </div>
                <div class="font-bold text-gray-900">{{ number_format($bid->amount, 0, ',', ' ') }} €</div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-gray-500">Aucune offre pour le moment.</div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $bids->links() }}
    </div>
</div>
@endsection
