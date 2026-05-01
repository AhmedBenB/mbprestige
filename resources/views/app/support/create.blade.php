@extends('layouts.app')
@section('title', 'Nouveau ticket – MBPRESTIGE')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold mb-6">Contacter le support</h1>

    <form method="POST" action="{{ route('app.support.store') }}" class="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
        @csrf
        <div>
            <label class="text-sm font-medium text-gray-700">Sujet</label>
            <input type="text" name="subject" value="{{ old('subject') }}" required class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Slug annonce (optionnel)</label>
            <input type="text" name="listing_slug" value="{{ old('listing_slug') }}" class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="ex: bmw-320d-2019">
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Priorité</label>
            <select name="priority" class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="normal">Normale</option>
                <option value="low">Faible</option>
                <option value="high">Haute</option>
                <option value="urgent">Urgente</option>
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Message</label>
            <textarea name="message" rows="7" required class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">{{ old('message') }}</textarea>
        </div>

        <div class="flex gap-2">
            <button class="bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg hover:bg-blue-800">Envoyer la demande</button>
            <a href="{{ route('app.support.index') }}" class="border border-gray-300 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-50">Annuler</a>
        </div>
    </form>
</div>
@endsection
