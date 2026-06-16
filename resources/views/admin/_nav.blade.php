<div class="bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl p-3 mb-6">
    <div class="flex flex-wrap items-center gap-2 text-sm font-medium">
        <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-[#d4af37]/10 text-[#d4af37]' : 'text-gray-400 hover:bg-[#222] hover:text-white' }}">
            Dashboard
        </a>
        <a href="{{ route('admin.listings.index') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ request()->routeIs('admin.listings.*') ? 'bg-[#d4af37]/10 text-[#d4af37]' : 'text-gray-400 hover:bg-[#222] hover:text-white' }}">
            Annonces
        </a>
        <a href="{{ route('admin.external_listings.index') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ request()->routeIs('admin.external_listings.*') ? 'bg-[#d4af37]/10 text-[#d4af37]' : 'text-gray-400 hover:bg-[#222] hover:text-white' }}">
            Annonces eCarsTrade
        </a>
        <a href="{{ route('admin.purchases.index') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ request()->routeIs('admin.purchases.*') ? 'bg-[#d4af37]/10 text-[#d4af37]' : 'text-gray-400 hover:bg-[#222] hover:text-white' }}">
            Ventes
        </a>
        <a href="{{ route('admin.payments.index') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ request()->routeIs('admin.payments.*') ? 'bg-[#d4af37]/10 text-[#d4af37]' : 'text-gray-400 hover:bg-[#222] hover:text-white' }}">
            Paiements
        </a>
        <a href="{{ route('admin.bids.index') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ request()->routeIs('admin.bids.*') ? 'bg-[#d4af37]/10 text-[#d4af37]' : 'text-gray-400 hover:bg-[#222] hover:text-white' }}">
            Offres
        </a>
        <a href="{{ route('admin.users.index') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ request()->routeIs('admin.users.*') ? 'bg-[#d4af37]/10 text-[#d4af37]' : 'text-gray-400 hover:bg-[#222] hover:text-white' }}">
            Clients
        </a>
        <a href="{{ route('admin.client_requests.index') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ request()->routeIs('admin.client_requests.*') ? 'bg-[#d4af37]/10 text-[#d4af37]' : 'text-gray-400 hover:bg-[#222] hover:text-white' }}">
            Demandes clients
        </a>
        <a href="{{ route('admin.support.index') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ request()->routeIs('admin.support.*') ? 'bg-[#d4af37]/10 text-[#d4af37]' : 'text-gray-400 hover:bg-[#222] hover:text-white' }}">
            Tickets support
        </a>
        <a href="{{ route('admin.settings.index') }}" class="px-3 py-1.5 rounded-lg transition-colors {{ request()->routeIs('admin.settings.*') ? 'bg-[#d4af37]/10 text-[#d4af37]' : 'text-gray-400 hover:bg-[#222] hover:text-white' }}">
            Paramètres
        </a>
    </div>
</div>
