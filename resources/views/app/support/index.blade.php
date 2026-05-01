@extends('layouts.app')
@section('title', 'Support – MBPRESTIGE')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Mes tickets support</h1>
        <a href="{{ route('app.support.create') }}" class="bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-800">
            Nouveau ticket
        </a>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Sujet</th>
                    <th class="px-4 py-3 text-left">Statut</th>
                    <th class="px-4 py-3 text-left">Priorité</th>
                    <th class="px-4 py-3 text-left">Dernier message</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tickets as $ticket)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">#{{ $ticket->id }}</td>
                    <td class="px-4 py-3">{{ $ticket->subject }}</td>
                    <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded bg-gray-100">{{ $ticket->status->label() }}</span></td>
                    <td class="px-4 py-3">{{ $ticket->priority->label() }}</td>
                    <td class="px-4 py-3">{{ $ticket->last_message_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('app.support.show', $ticket) }}" class="text-blue-700 hover:underline">Ouvrir</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Aucun ticket pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">{{ $tickets->links() }}</div>
    </div>
</div>
@endsection
