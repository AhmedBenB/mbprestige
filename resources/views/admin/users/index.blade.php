@extends('layouts.app')
@section('title', 'Admin – Clients')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @include('admin._nav')
    <h1 class="text-2xl font-bold mb-6">Clients et utilisateurs</h1>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Rôle</label>
            <select name="role" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                <option value="client" @selected(request('role') === 'client')>client</option>
                <option value="admin" @selected(request('role') === 'admin')>admin</option>
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Statut</label>
            <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                <option value="active" @selected(request('status') === 'active')>active</option>
                <option value="disabled" @selected(request('status') === 'disabled')>disabled</option>
            </select>
        </div>
        <div class="flex-1 min-w-64">
            <label class="text-xs text-gray-500 block mb-1">Recherche</label>
            <input type="text" name="search" value="{{ request('search') }}" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="email, nom, téléphone">
        </div>
        <button class="bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Filtrer</button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">ID</th>
                    <th class="px-4 py-3 text-left">Nom</th>
                    <th class="px-4 py-3 text-left">Email</th>
                    <th class="px-4 py-3 text-left">Téléphone</th>
                    <th class="px-4 py-3 text-left">Organisation</th>
                    <th class="px-4 py-3 text-left">Rôle/Statut</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $user)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">#{{ $user->id }}</td>
                    <td class="px-4 py-3">{{ $user->name }}</td>
                    <td class="px-4 py-3">{{ $user->email }}</td>
                    <td class="px-4 py-3">{{ $user->phone ?? 'N/A' }}</td>
                    <td class="px-4 py-3">{{ $user->organization?->name ?? 'N/A' }}</td>
                    <td class="px-4 py-3">{{ $user->role }} / {{ $user->status }}</td>
                    <td class="px-4 py-3 text-right">
                        <details class="inline-block text-left">
                            <summary class="cursor-pointer text-blue-700 hover:underline">Modifier</summary>
                            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-2 p-3 border border-gray-200 rounded-lg bg-gray-50 w-72">
                                @csrf
                                @method('PATCH')
                                <input type="email" name="email" value="{{ $user->email }}" class="w-full border border-gray-200 rounded px-2 py-1.5 text-xs mb-2" required>
                                <input type="text" name="phone" value="{{ $user->phone }}" class="w-full border border-gray-200 rounded px-2 py-1.5 text-xs mb-2" placeholder="Téléphone">
                                <select name="role" class="w-full border border-gray-200 rounded px-2 py-1.5 text-xs mb-2">
                                    <option value="client" @selected($user->role === 'client')>client</option>
                                    <option value="admin" @selected($user->role === 'admin')>admin</option>
                                </select>
                                <select name="status" class="w-full border border-gray-200 rounded px-2 py-1.5 text-xs mb-2">
                                    <option value="active" @selected($user->status === 'active')>active</option>
                                    <option value="disabled" @selected($user->status === 'disabled')>disabled</option>
                                </select>
                                <button class="w-full bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded">Enregistrer</button>
                            </form>
                        </details>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucun utilisateur.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">{{ $users->links() }}</div>
    </div>
</div>
@endsection
