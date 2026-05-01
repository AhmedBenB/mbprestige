<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [],
            'message' => 'Notifications à brancher côté métier.',
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        return back()->with('success', 'Notification marquée comme lue.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        return back()->with('success', 'Toutes les notifications sont marquées comme lues.');
    }
}
