{{-- Composant réutilisable : sidebar filtres --}}
<form method="GET" id="filter-form" class="space-y-6">
    {{-- Conserver le tri courant --}}
    @if(request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif

    {{-- Marque --}}
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

    {{-- Carburant --}}
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
                {{ $fuel }}
            </label>
            @endforeach
            @if(request('fuel'))
                <a href="{{ request()->fullUrlWithQuery(['fuel' => null]) }}"
                   class="text-xs text-blue-700 hover:underline">Effacer</a>
            @endif
        </div>
    </div>

    {{-- Boîte de vitesses --}}
    <div x-data="{ open: true }">
        <button type="button" @click="open = !open"
                class="flex w-full justify-between items-center font-semibold text-sm text-gray-700 mb-2">
            Boîte
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
                {{ $gearbox }}
            </label>
            @endforeach
        </div>
    </div>

    {{-- Année --}}
    <div x-data="{ open: true }">
        <button type="button" @click="open = !open"
                class="flex w-full justify-between items-center font-semibold text-sm text-gray-700 mb-2">
            Année
            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open" class="flex gap-2">
            <input type="number" name="year_min" value="{{ request('year_min') }}"
                   placeholder="De" min="2000" max="{{ date('Y') }}"
                   class="w-1/2 border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="number" name="year_max" value="{{ request('year_max') }}"
                   placeholder="À" min="2000" max="{{ date('Y') }}"
                   class="w-1/2 border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    {{-- Kilométrage --}}
    <div x-data="{ open: true }">
        <button type="button" @click="open = !open"
                class="flex w-full justify-between items-center font-semibold text-sm text-gray-700 mb-2">
            Kilométrage max
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

    {{-- Prix --}}
    <div x-data="{ open: true }">
        <button type="button" @click="open = !open"
                class="flex w-full justify-between items-center font-semibold text-sm text-gray-700 mb-2">
            Prix (€)
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

    {{-- TVA --}}
    <div>
        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer font-medium">
            <input type="checkbox" name="vat" value="1" @checked(request('vat')=='1')
                   onchange="document.getElementById('filter-form').submit()"
                   class="rounded text-blue-600 focus:ring-blue-500">
            TVA déductible uniquement
        </label>
    </div>

    {{-- Pays d'origine --}}
    <div x-data="{ open: false }">
        <button type="button" @click="open = !open"
                class="flex w-full justify-between items-center font-semibold text-sm text-gray-700 mb-2">
            Pays d'origine
            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open">
            <select name="country" onchange="document.getElementById('filter-form').submit()"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Tous</option>
                @foreach($filters['countries'] as $country)
                    <option value="{{ $country }}" @selected(request('country')===$country)>{{ $country }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Boutons --}}
    <div class="flex gap-2 pt-2">
        <button type="submit"
                class="flex-1 bg-blue-700 text-white text-sm font-semibold py-2 rounded-lg hover:bg-blue-800">
            Appliquer
        </button>
        <a href="{{ url()->current() }}"
           class="flex-1 text-center border border-gray-300 text-sm text-gray-600 py-2 rounded-lg hover:bg-gray-50">
            Réinitialiser
        </a>
    </div>
</form>
