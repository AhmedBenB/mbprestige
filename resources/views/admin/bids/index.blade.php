@extends('layouts.app')
@section('title', 'Admin – Offres')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')
    <h1 class="text-2xl font-bold mb-6">Offres clients</h1>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Statut</label>
            <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                @foreach(['pending','leading','outbid','won_pending_validation','accepted','rejected','cancelled','expired'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-64">
            <label class="text-xs text-gray-500 block mb-1">Recherche</label>
            <input type="text" name="search" value="{{ request('search') }}" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="ID, email, titre">
        </div>
        <button class="bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Filtrer</button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">ID</th>
                    <th class="px-4 py-3 text-left">Client</th>
                    <th class="px-4 py-3 text-left">Annonce</th>
                    <th class="px-4 py-3 text-right">Montant</th>
                    <th class="px-4 py-3 text-left">Statut</th>
                    <th class="px-4 py-3 text-left">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($bids as $bid)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">#{{ $bid->id }}</td>
                    <td class="px-4 py-3">{{ $bid->user?->email ?? 'N/A' }}</td>
                    <td class="px-4 py-3">{{ $bid->listing?->title ?? 'N/A' }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $bid->amount, 0, ',', ' ') }} {{ $bid->currency }}</td>
                    <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded bg-gray-100">{{ $bid->status->value }}</span></td>
                    <td class="px-4 py-3">{{ $bid->placed_at?->format('d/m/Y H:i') ?? $bid->created_at?->format('d/m/Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aucune offre.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">{{ $bids->links() }}</div>
    </div>
</div>
@endsection
