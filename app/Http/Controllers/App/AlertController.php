<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(): View
    {
        $alerts = SavedSearch::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('app.dashboard.index', compact('alerts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'filters_json' => ['required', 'array'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_push' => ['nullable', 'boolean'],
        ]);

        SavedSearch::create([
            'user_id' => auth()->id(),
            'name' => $data['name'],
            'filters_json' => $data['filters_json'],
            'notify_email' => (bool) ($data['notify_email'] ?? true),
            'notify_push' => (bool) ($data['notify_push'] ?? false),
        ]);

        return back()->with('success', 'Alerte enregistrée.');
    }

    public function destroy(SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($savedSearch->user_id === auth()->id(), 403);
        $savedSearch->delete();

        return back()->with('success', 'Alerte supprimée.');
    }
}
