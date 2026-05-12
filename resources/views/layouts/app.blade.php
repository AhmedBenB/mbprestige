<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'MBPRESTIGE – Marketplace automobile professionnelle')</title>
    <meta name="description" content="@yield('meta_description', 'Achetez des véhicules d\'occasion en gros. Enchères, prix fixes, stock partenaire.')">
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @endif
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-900">

    {{-- Header --}}
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                {{-- Logo --}}
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-xl text-blue-700">
                    <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
                    MBPRESTIGE
                </a>

                {{-- Navigation principale --}}
                <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
                    <a href="{{ route('catalog.index') }}" class="hover:text-blue-700 @active('catalogue*') text-blue-700 @endactive">Tous les véhicules</a>
                    <a href="{{ route('catalog.auctions') }}" class="hover:text-blue-700">Enchères</a>
                    <a href="{{ route('catalog.fixed_prices') }}" class="hover:text-blue-700">Prix fixes</a>
                    <a href="{{ route('catalog.stock') }}" class="hover:text-blue-700">Notre stock</a>
                    <a href="{{ route('how_it_works') }}" class="hover:text-blue-700">Comment ça marche</a>
                </nav>

                {{-- Auth --}}
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('app.profile.show') }}" class="text-sm font-medium text-gray-700 hover:text-blue-700">Mon espace</a>
                        @if(\Illuminate\Support\Facades\Route::has('admin.listings.index') && auth()->user()->isAdmin())
                            <a href="{{ route('admin.listings.index') }}" class="text-sm font-medium text-orange-600 hover:text-orange-700">Admin</a>
                        @endif
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-gray-700 hover:text-blue-700">
                                Déconnexion
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-blue-700">Connexion</a>
                        <a href="{{ route('register') }}" class="bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-800">Inscription</a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 px-6 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-6 py-3 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Contenu principal --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-gray-900 text-gray-300 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-2 md:grid-cols-4 gap-8 text-sm">
            <div>
                <h4 class="font-semibold text-white mb-3">Catalogue</h4>
                <ul class="space-y-2">
                    <li><a href="{{ route('catalog.auctions') }}" class="hover:text-white">Enchères</a></li>
                    <li><a href="{{ route('catalog.fixed_prices') }}" class="hover:text-white">Prix fixes</a></li>
                    <li><a href="{{ route('catalog.stock') }}" class="hover:text-white">Notre stock</a></li>
                    <li><a href="{{ route('brands.index') }}" class="hover:text-white">Par marque</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-white mb-3">Informations</h4>
                <ul class="space-y-2">
                    <li><a href="{{ route('how_it_works') }}" class="hover:text-white">Comment ça marche</a></li>
                    <li><a href="{{ route('costs') }}" class="hover:text-white">Frais et commissions</a></li>
                    <li><a href="{{ route('delivery') }}" class="hover:text-white">Livraison</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-white">FAQ</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-white mb-3">Espace pro</h4>
                <ul class="space-y-2">
                    <li><a href="{{ route('professionals') }}" class="hover:text-white">Professionnels</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white">Créer un compte</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-white">Contact</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-white mb-3">Légal</h4>
                <ul class="space-y-2">
                    <li><a href="{{ route('mentions_legales') }}" class="hover:text-white">Mentions légales</a></li>
                    <li><a href="{{ route('cgv') }}" class="hover:text-white">CGV</a></li>
                    <li><a href="{{ route('privacy') }}" class="hover:text-white">Confidentialité</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-800 text-center text-xs text-gray-500 py-4">
            &copy; {{ date('Y') }} MBPRESTIGE. Tous droits réservés.
        </div>
    </footer>

</body>
</html>
