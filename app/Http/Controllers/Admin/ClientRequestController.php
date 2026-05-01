<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = SavedSearch::query()
            ->with('user')
            ->when($request->search, function ($q) use ($request) {
                $term = trim((string) $request->search);
                $q->where(function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($sq) => $sq->where('email', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.client-requests.index', compact('requests'));
    }
}
