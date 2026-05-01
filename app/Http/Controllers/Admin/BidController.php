<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BidController extends Controller
{
    public function index(Request $request): View
    {
        $bids = Bid::query()
            ->with(['user', 'listing'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, function ($q) use ($request) {
                $term = trim((string) $request->search);
                $q->where(function ($query) use ($term) {
                    $query->where('id', $term)
                        ->orWhereHas('user', fn ($sq) => $sq->where('email', 'like', "%{$term}%"))
                        ->orWhereHas('listing', fn ($sq) => $sq->where('title', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.bids.index', compact('bids'));
    }
}
