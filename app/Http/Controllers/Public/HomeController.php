<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ExternalListing;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featuredListings = ExternalListing::query()
            ->with('latestPriceEstimate')
            ->where('status', ExternalListing::STATUS_PUBLISHED)
            ->orderByDesc('views_count')
            ->take(8)
            ->get();

        $latestListings = ExternalListing::query()
            ->with('latestPriceEstimate')
            ->where('status', ExternalListing::STATUS_PUBLISHED)
            ->latest('updated_at')
            ->take(12)
            ->get();

        $liveAuctions = ExternalListing::query()
            ->with('latestPriceEstimate')
            ->where('status', ExternalListing::STATUS_PUBLISHED)
            ->where('listing_type', 'like', 'auction_%')
            ->where(function ($query): void {
                $query->whereNull('auction_end_at')
                    ->orWhere('auction_end_at', '>', now());
            })
            ->orderBy('auction_end_at')
            ->take(6)
            ->get();

        $stats = [
            'total_vehicles' => ExternalListing::query()->where('status', ExternalListing::STATUS_PUBLISHED)->count(),
            'live_auctions'  => $liveAuctions->count(),
            'brands'         => ExternalListing::query()
                ->where('status', ExternalListing::STATUS_PUBLISHED)
                ->whereNotNull('make')
                ->distinct('make')
                ->count('make'),
        ];

        return view('public.home', compact('featuredListings', 'latestListings', 'liveAuctions', 'stats'));
    }
}
