<div class="bg-white border border-gray-200 rounded-xl p-3 mb-6">
    <div class="flex flex-wrap items-center gap-2 text-sm font-medium">
        <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.dashboard') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Dashboard
        </a>
        <a href="{{ route('admin.listings.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.listings.*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Annonces
        </a>
        <a href="{{ route('admin.external_listings.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.external_listings.*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Annonces eCarsTrade
        </a>
        <a href="{{ route('admin.purchases.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.purchases.*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Ventes
        </a>
        <a href="{{ route('admin.payments.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.payments.*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Paiements
        </a>
        <a href="{{ route('admin.bids.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.bids.*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Offres
        </a>
        <a href="{{ route('admin.users.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.users.*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Clients
        </a>
        <a href="{{ route('admin.client_requests.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.client_requests.*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Demandes clients
        </a>
        <a href="{{ route('admin.support.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.support.*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Tickets support
        </a>
        <a href="{{ route('admin.settings.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.settings.*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Paramètres
        </a>
    </div>
</div>
