@extends('layouts.app')
@section('title', 'Admin – Paiements')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')
    <h1 class="text-2xl font-bold mb-6">Paiements</h1>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Statut</label>
            <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                @foreach(['pending','paid','failed','cancelled','refunded'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Type</label>
            <select name="type" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                @foreach(['deposit','balance','full'] as $s)
                    <option value="{{ $s }}" @selected(request('type') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-64">
            <label class="text-xs text-gray-500 block mb-1">Recherche</label>
            <input type="text" name="search" value="{{ request('search') }}" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="ID, session, intent, email, titre">
        </div>
        <button class="bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Filtrer</button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">ID</th>
                    <th class="px-4 py-3 text-left">Client</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Statut</th>
                    <th class="px-4 py-3 text-right">Montant</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($payments as $payment)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">#{{ $payment->id }}</td>
                    <td class="px-4 py-3">{{ $payment->user?->email ?? 'N/A' }}</td>
                    <td class="px-4 py-3">{{ $payment->type->value }}</td>
                    <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded bg-gray-100">{{ $payment->status->value }}</span></td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $payment->currency }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.payments.show', $payment) }}" class="text-blue-700 hover:underline">Détail</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aucun paiement.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">{{ $payments->links() }}</div>
    </div>
</div>
@endsection
