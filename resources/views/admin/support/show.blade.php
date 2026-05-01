@extends('layouts.app')
@section('title', 'Admin – Ticket support')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')

    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">Ticket #{{ $ticket->id }}</h1>
                <p class="text-sm text-gray-500">{{ $ticket->subject }}</p>
            </div>
            <a href="{{ route('admin.support.index') }}" class="text-sm text-blue-700 hover:underline">Retour liste</a>
        </div>

        <div class="grid md:grid-cols-2 gap-6 text-sm mt-5">
            <div class="space-y-1">
                <p><span class="text-gray-500">Client:</span> {{ $ticket->user?->name ?? 'N/A' }} ({{ $ticket->user?->email ?? 'N/A' }})</p>
                <p><span class="text-gray-500">Société:</span> {{ $ticket->user?->organization?->name ?? 'N/A' }}</p>
                <p><span class="text-gray-500">Annonce liée:</span> {{ $ticket->listing?->title ?? 'N/A' }}</p>
            </div>
            <div class="space-y-1">
                <p><span class="text-gray-500">Statut:</span> {{ $ticket->status->label() }}</p>
                <p><span class="text-gray-500">Priorité:</span> {{ $ticket->priority->label() }}</p>
                <p><span class="text-gray-500">Assigné:</span> {{ $ticket->handler?->email ?? 'Non assigné' }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-3 mb-6">
        @foreach($ticket->messages()->with('user')->oldest('id')->get() as $message)
            <div class="rounded-lg p-3 {{ $message->is_internal ? 'bg-amber-50 border border-amber-200' : ($message->author_type === 'admin' ? 'bg-blue-50 border border-blue-100' : 'bg-gray-50 border border-gray-100') }}">
                <div class="text-xs text-gray-500 mb-1">
                    {{ $message->author_type === 'admin' ? 'Admin' : 'Client' }}{{ $message->is_internal ? ' · Note interne' : '' }} · {{ $message->created_at?->format('d/m/Y H:i') }}
                </div>
                <p class="text-sm text-gray-800 whitespace-pre-line">{{ $message->message }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" class="bg-white border border-gray-200 rounded-xl p-4">
            @csrf
            <h2 class="font-semibold mb-3">Répondre au client</h2>
            <textarea name="message" rows="5" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-2"></textarea>
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 mb-3">
                <input type="checkbox" name="is_internal" value="1">
                Note interne (non visible client)
            </label>
            <button class="w-full bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg hover:bg-blue-800">Envoyer</button>
        </form>

        <form method="POST" action="{{ route('admin.support.status', $ticket) }}" class="bg-white border border-gray-200 rounded-xl p-4">
            @csrf
            <h2 class="font-semibold mb-3">Changer le statut</h2>
            <select name="status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-3">
                @foreach(['open','pending_admin','pending_customer','resolved','closed'] as $status)
                    <option value="{{ $status }}" @selected($ticket->status->value === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <button class="w-full bg-emerald-600 text-white font-semibold px-4 py-2 rounded-lg hover:bg-emerald-700">Mettre à jour</button>
        </form>
    </div>
</div>
@endsection
