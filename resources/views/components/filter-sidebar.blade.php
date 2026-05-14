@php
    $fuelLabels = [
        'diesel' => 'Diesel',
        'essence' => 'Essence',
        'hybride' => 'Hybride',
        'electrique' => 'Electrique',
        'gpl' => 'GPL',
        'gaz' => 'Gaz',
    ];

    $gearboxLabels = [
        'automatic' => 'Automatique',
        'manual' => 'Manuelle',
        'semi-automatic' => 'Semi-automatique',
    ];
@endphp

<form method="GET" id="filter-form" class="space-y-6">
    @if(request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif

    <div x-data="{ open: true }">
        <button type="button" @click="open = !open"
                class="flex w-full justify-between items-center font-semibold text-sm text-gray-700 mb-2">
            Marque
            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open">
            <select name="make" onchange="document.getElementById('filter-form').submit()"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Toutes les marques</option>
                @foreach($filters['makes'] as $make)
                    <option value="{{ $make }}" @selected(request('make') === $make)>{{ $make }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div x-data="{ open: true }">
        <button type="button" @click="open = !open"
                class="flex w-full justify-between items-center font-semibold text-sm text-gray-700 mb-2">
            Carburant
            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open" class="space-y-1.5">
            @foreach($filters['fuels'] as $fuel)
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-gray-900">
                <input type="radio" name="fuel" value="{{ $fuel }}"
                       @checked(request('fuel') === $fuel)
                       onchange="document.getElementById('filter-form').submit()"
                       class="text-blue-600 focus:ring-blue-500">
                {{ $fuelLabels[$fuel] ?? ucfirst($fuel) }}
            </label>
            @endforeach
            @if(request('fuel'))
                <a href="{{ request()->fullUrlWithQuery(['fuel' => null]) }}"
                   class="text-xs text-blue-700 hover:underline">Effacer</a>
            @endif
        </div>
    </div>

    <div x-data="{ open: true }">
        <button type="button" @click="open = !open"
                class="flex w-full justify-between items-center font-semibold text-sm text-gray-700 mb-2">
            Boite
            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open" class="space-y-1.5">
            @foreach($filters['gearboxes'] as $gearbox)
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-gray-900">
                <input type="radio" name="gearbox" value="{{ $gearbox }}"
                       @checked(request('gearbox') === $gearbox)
                       onchange="document.getElementById('filter-form').submit()"
                       class="text-blue-600 focus:ring-blue-500">
                {{ $gearboxLabels[$gearbox] ?? ucfirst($gearbox) }}
            </label>
            @endforeach
            @if(request('gearbox'))
                <a href="{{ request()->fullUrlWithQuery(['gearbox' => null]) }}"
                   class="text-xs text-blue-700 hover:underline">Effacer</a>
            @endif
        </div>
    </div>

    <div x-data="{ open: true }">
        <button type="button" @click="open = !open"
                class="flex w-full justify-between items-center font-semibold text-sm text-gray-700 mb-2">
            Annee
            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open" class="flex gap-2">
            <input type="number" name="year_min" value="{{ request('year_min') }}"
                   placeholder="De" min="2000" max="{{ date('Y') }}"
                   class="w-1/2 border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="number" name="year_max" value="{{ request('year_max') }}"
                   placeholder="A" min="2000" max="{{ date('Y') }}"
                   class="w-1/2 border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    <div x-data="{ open: true }">
        <button type="button" @click="open = !open"
                class="flex w-full justify-between items-center font-semibold text-sm text-gray-700 mb-2">
            Kilometrage max
            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open">
            <input type="number" name="km_max" value="{{ request('km_max') }}"
                   placeholder="Ex : 100000"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    <div x-data="{ open: true }">
        <button type="button" @click="open = !open"
                class="flex w-full justify-between items-center font-semibold text-sm text-gray-700 mb-2">
            Prix (EUR)
            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open" class="flex gap-2">
            <input type="number" name="price_min" value="{{ request('price_min') }}"
                   placeholder="Min"
                   class="w-1/2 border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="number" name="price_max" value="{{ request('price_max') }}"
                   placeholder="Max"
                   class="w-1/2 border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    <div class="flex gap-2 pt-2">
        <button type="submit"
                class="flex-1 bg-blue-700 text-white text-sm font-semibold py-2 rounded-lg hover:bg-blue-800">
            Appliquer
        </button>
        <a href="{{ url()->current() }}"
           class="flex-1 text-center border border-gray-300 text-sm text-gray-600 py-2 rounded-lg hover:bg-gray-50">
            Reinitialiser
        </a>
    </div>
</form>
