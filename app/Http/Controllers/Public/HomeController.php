<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featuredListings = Listing::published()
            ->featured()
            ->with(['vehicle', 'coverImage', 'auction'])
            ->take(8)
            ->get();

        $latestListings = Listing::published()
            ->with(['vehicle', 'coverImage', 'auction'])
            ->latest('published_at')
            ->take(12)
            ->get();

        $liveAuctions = Listing::published()
            ->auctions()
            ->live()
            ->with(['vehicle', 'coverImage', 'auction'])
            ->orderBy('ends_at')
            ->take(6)
            ->get();

        $stats = [
            'total_vehicles' => Listing::published()->count(),
            'live_auctions'  => $liveAuctions->count(),
            'brands'         => \App\Models\Vehicle::distinct('make')->count(),
        ];

        return view('public.home', compact('featuredListings', 'latestListings', 'liveAuctions', 'stats'));
    }
}
