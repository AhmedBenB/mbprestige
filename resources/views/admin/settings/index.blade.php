@extends('layouts.app')
@section('title', 'Paramètres - Admin MBPRESTIGE')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')

    <h1 class="text-2xl font-bold text-white mb-6">Paramètres</h1>

    @if(session('success'))
        <div class="bg-green-900/30 border border-green-600 text-green-400 rounded-lg px-4 py-3 mb-6 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-900/30 border border-red-600 text-red-400 rounded-lg px-4 py-3 mb-6 text-sm">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="bg-[#1a1a1a] border border-[#2a2a2a] rounded-xl p-6 max-w-lg">
        <h2 class="font-semibold text-white text-lg mb-1">Code d'inscription</h2>
        <p class="text-gray-400 text-sm mb-5">Les nouveaux utilisateurs doivent saisir ce code pour créer un compte.</p>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Code de parrainage</label>
                <input type="text" name="registration_code"
                       value="{{ old('registration_code', $registrationCode) }}"
                       required maxlength="50"
                       class="w-full bg-[#222] border border-[#333] text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#d4af37] uppercase tracking-widest"
                       placeholder="MBP95">
            </div>

            <button type="submit"
                    class="bg-[#d4af37] hover:bg-[#b8911f] text-black font-bold px-6 py-2.5 rounded-xl text-sm transition-colors">
                Enregistrer
            </button>
        </form>
    </div>
</div>
@endsection
