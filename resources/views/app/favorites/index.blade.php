@extends('layouts.app')
@section('title', 'Mes favoris – MBPRESTIGE')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Mes favoris</h1>

    @if($favorites->isEmpty())
        <div class="bg-white border border-gray-200 rounded-xl p-8 text-center text-gray-500">
            Aucun favori pour le moment.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($favorites as $favorite)
                @include('components.listing-card', ['listing' => $favorite->listing])
            @endforeach
        </div>

        <div class="mt-6">
            {{ $favorites->links() }}
        </div>
    @endif
</div>
@endsection
