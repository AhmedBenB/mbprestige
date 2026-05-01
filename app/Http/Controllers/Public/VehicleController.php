<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Auctions\AuctionStateResolver;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function __construct(private readonly AuctionStateResolver $resolver) {}

    public function show(Listing $listing): View
    {
        abort_unless($listing->isPublished(), 404);

        $listing->load(['vehicle', 'images', 'documents', 'attributes', 'auction', 'source']);

        $timerPayload = $listing->isAuction()
            ? $this->resolver->toFrontPayload($listing, auth()->user())
            : null;

        $similarListings = Listing::published()
            ->whereHas('vehicle', fn ($q) =>
                $q->where('make', $listing->vehicle->make)
                  ->where('id', '!=', $listing->vehicle_id))
            ->with(['vehicle', 'coverImage'])
            ->take(6)
            ->get();

        $attributes = $listing->attributes->groupBy('group_name');

        return view('public.vehicule.show', compact(
            'listing', 'timerPayload', 'similarListings', 'attributes'
        ));
    }
}
