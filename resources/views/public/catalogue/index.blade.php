@extends('layouts.app')

@section('title', 'Catalogue vehicules - MBPRESTIGE')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Catalogue vehicules</h1>
        <p class="text-gray-500 text-sm mt-1">{{ $listings->total() }} vehicule(s) disponible(s)</p>
    </div>

    <div class="flex gap-8" x-data="{ filtersOpen: false }">

        <aside class="hidden lg:block w-64 flex-shrink-0">
            @include('components.filter-sidebar', ['filters' => $filters])
        </aside>

        <div class="lg:hidden fixed inset-0 z-50 bg-black/50" x-show="filtersOpen" x-cloak @click="filtersOpen=false">
            <div class="absolute left-0 top-0 h-full w-80 bg-white overflow-y-auto p-4" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <span class="font-semibold">Filtres</span>
                    <button @click="filtersOpen=false" class="text-gray-400 hover:text-gray-600">x</button>
                </div>
                @include('components.filter-sidebar', ['filters' => $filters])
            </div>
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-5 gap-4 flex-wrap">
                <button @click="filtersOpen=true"
                        class="lg:hidden flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M10 12h4"/>
                    </svg>
                    Filtres
                </button>

                <div class="flex flex-wrap gap-2 flex-1">
                    @foreach(request()->except(['page','sort']) as $key => $val)
                        @if($val)
                        @php
                            $label = (string) $val;
                            if ($key === 'mode') {
                                $label = match ((string) $val) {
                                    'fixed_prices' => 'Prix fixes',
                                    'auctions' => 'Encheres',
                                    'stock' => 'Stock',
                                    default => (string) $val,
                                };
                            }
                        @endphp
                        <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-medium px-2 py-1 rounded-full">
                            {{ $label }}
                            <a href="{{ request()->fullUrlWithQuery([$key => null]) }}" class="hover:text-blue-900">x</a>
                        </span>
                        @endif
                    @endforeach
                </div>

                <form method="GET" id="sort-form">
                    @foreach(request()->except('sort') as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <select name="sort" onchange="document.getElementById('sort-form').submit()"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="published_at" @selected(request('sort','published_at')==='published_at')>Plus recents</option>
                        <option value="price_asc"    @selected(request('sort')==='price_asc')>Prix croissant</option>
                        <option value="price_desc"   @selected(request('sort')==='price_desc')>Prix decroissant</option>
                        <option value="ends_at"      @selected(request('sort')==='ends_at')>Fin enchere</option>
                    </select>
                </form>
            </div>

            @if($listings->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                    @foreach($listings as $listing)
                        @include('components.external-listing-card', ['listing' => $listing])
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $listings->links() }}
                </div>
            @else
                <div class="text-center py-20 text-gray-400">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-200" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/>
                    </svg>
                    <p class="text-lg font-medium text-gray-500">Aucun vehicule trouve</p>
                    <p class="text-sm mt-1">Essayez de modifier vos filtres.</p>
                    <a href="{{ route('catalog.index') }}"
                       class="inline-block mt-4 text-blue-700 font-semibold hover:underline text-sm">
                        Reinitialiser les filtres
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
