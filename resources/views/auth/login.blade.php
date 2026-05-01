@extends('layouts.app')
@section('title', 'Connexion – MBPRESTIGE')

@section('content')
<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-blue-700 font-bold text-xl">
                <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/></svg>
                MBPRESTIGE
            </a>
            <h1 class="text-2xl font-bold mt-4 text-gray-900">Connexion</h1>
            <p class="text-gray-500 text-sm mt-1">Accédez à votre espace professionnel</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-6 text-sm text-red-700">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email professionnel</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-400 @enderror"
                           placeholder="vous@societe.fr">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Mot de passe</label>
                    <input type="password" name="password" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="••••••••">
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded text-blue-600">
                        Se souvenir de moi
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm text-blue-700 hover:underline">
                        Mot de passe oublié ?
                    </a>
                </div>
                <button type="submit"
                        class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-3 rounded-xl text-sm transition-colors">
                    Se connecter
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-500">
                Pas encore de compte ?
                <a href="{{ route('register') }}" class="text-blue-700 font-semibold hover:underline">S'inscrire</a>
            </div>
        </div>
    </div>
</div>
@endsection
