<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppAlertController extends Controller
{
    public function index(): JsonResponse
    {
        $alerts = SavedSearch::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json(['data' => $alerts]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'filters_json' => ['required', 'array'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_push' => ['nullable', 'boolean'],
        ]);

        $alert = SavedSearch::create([
            'user_id' => auth()->id(),
            'name' => $data['name'],
            'filters_json' => $data['filters_json'],
            'notify_email' => (bool) ($data['notify_email'] ?? true),
            'notify_push' => (bool) ($data['notify_push'] ?? false),
        ]);

        return response()->json([
            'message' => 'Alerte enregistrée.',
            'data' => $alert,
        ], 201);
    }

    public function destroy(SavedSearch $savedSearch): JsonResponse
    {
        abort_unless($savedSearch->user_id === auth()->id(), 403);

        $savedSearch->delete();

        return response()->json(['message' => 'Alerte supprimée.']);
    }
}
