@extends('layouts.app')
@section('title', 'Admin – Demandes clients')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')
    <h1 class="text-2xl font-bold mb-6">Demandes clients (alertes enregistrées)</h1>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-64">
            <label class="text-xs text-gray-500 block mb-1">Recherche</label>
            <input type="text" name="search" value="{{ request('search') }}" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="email ou nom de demande">
        </div>
        <button class="bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Filtrer</button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">ID</th>
                    <th class="px-4 py-3 text-left">Client</th>
                    <th class="px-4 py-3 text-left">Nom demande</th>
                    <th class="px-4 py-3 text-left">Email notif</th>
                    <th class="px-4 py-3 text-left">Push notif</th>
                    <th class="px-4 py-3 text-left">Créée le</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($requests as $requestItem)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">#{{ $requestItem->id }}</td>
                    <td class="px-4 py-3">{{ $requestItem->user?->email ?? 'N/A' }}</td>
                    <td class="px-4 py-3">{{ $requestItem->name }}</td>
                    <td class="px-4 py-3">{{ $requestItem->notify_email ? 'Oui' : 'Non' }}</td>
                    <td class="px-4 py-3">{{ $requestItem->notify_push ? 'Oui' : 'Non' }}</td>
                    <td class="px-4 py-3">{{ $requestItem->created_at?->format('d/m/Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aucune demande client.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">{{ $requests->links() }}</div>
    </div>
</div>
@endsection
