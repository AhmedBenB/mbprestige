@extends('layouts.app')
@section('title', 'Connexion - MBPRESTIGE')

@section('content')
<div class="min-h-screen bg-[#0a0a0a] flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-[#d4af37] font-bold text-xl">
                <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/></svg>
                MBPRESTIGE
            </a>
            <h1 class="text-2xl font-bold mt-4 text-white">Connexion</h1>
            <p class="text-gray-500 text-sm mt-1">Connectez-vous avec votre email et votre mot de passe</p>
        </div>

        <div class="bg-[#1a1a1a] rounded-2xl border border-[#2a2a2a] p-8">
            @if($errors->any())
                <div class="bg-red-900/30 border border-red-600 rounded-lg p-3 mb-6 text-sm text-red-400">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37] @error('email') border-red-500 @enderror"
                           placeholder="vous@example.com">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Mot de passe</label>
                    <input type="password" name="password" required
                           class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37]"
                           placeholder="********">
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded bg-[#222] border-[#444] text-[#d4af37]">
                        Se souvenir de moi
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm text-[#d4af37] hover:text-[#e8c96d]">
                        Mot de passe oublié ?
                    </a>
                </div>

                <button type="submit"
                        class="w-full bg-[#d4af37] hover:bg-[#b8911f] text-black font-bold py-3 rounded-xl text-sm transition-colors">
                    Se connecter
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-500">
                Pas encore de compte ?
                <a href="{{ route('register') }}" class="text-[#d4af37] font-semibold hover:text-[#e8c96d]">Inscrivez-vous</a>
            </div>
        </div>
    </div>
</div>
@endsection
