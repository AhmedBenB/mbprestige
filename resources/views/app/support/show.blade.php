@extends('layouts.app')
@section('title', 'Ticket support – MBPRESTIGE')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <a href="{{ route('app.support.index') }}" class="text-sm text-blue-700 hover:underline">← Retour tickets</a>
        <h1 class="text-2xl font-bold mt-2">Ticket #{{ $ticket->id }} · {{ $ticket->subject }}</h1>
        <p class="text-sm text-gray-500">
            Statut: {{ $ticket->status->label() }} · Priorité: {{ $ticket->priority->label() }}
        </p>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 mb-6">
        @forelse($ticket->messages as $message)
            <div class="rounded-lg p-3 {{ $message->author_type === 'admin' ? 'bg-blue-50 border border-blue-100' : 'bg-gray-50 border border-gray-100' }}">
                <div class="text-xs text-gray-500 mb-1">
                    {{ $message->author_type === 'admin' ? 'Support MBPRESTIGE' : 'Vous' }} · {{ $message->created_at?->format('d/m/Y H:i') }}
                </div>
                <p class="text-sm text-gray-800 whitespace-pre-line">{{ $message->message }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-400">Aucun message.</p>
        @endforelse
    </div>

    @if(!in_array($ticket->status->value, ['resolved', 'closed']))
    <form method="POST" action="{{ route('app.support.reply', $ticket) }}" class="bg-white border border-gray-200 rounded-xl p-4">
        @csrf
        <label class="text-sm font-medium text-gray-700">Ajouter une réponse</label>
        <textarea name="message" rows="5" required class="mt-2 w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"></textarea>
        <button class="mt-3 bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg hover:bg-blue-800">Envoyer</button>
    </form>
    @endif
</div>
@endsection
