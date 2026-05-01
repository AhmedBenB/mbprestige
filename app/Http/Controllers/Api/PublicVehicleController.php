<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Listings\AuctionPriceMasker;
use App\Services\Payment\DepositCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicVehicleController extends Controller
{
    public function __construct(
        private readonly AuctionPriceMasker $priceMasker,
        private readonly DepositCalculator $depositCalculator
    ) {}

    public function show(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->isPublished(), 404);

        $listing->load(['vehicle', 'coverImage', 'images', 'documents', 'attributes', 'auction']);

        $canViewAuctionPrices = $this->canViewAuctionPrices($request);
        $this->priceMasker->maskListing($listing, $canViewAuctionPrices);

        return response()->json([
            'data' => $listing,
            'meta' => [
                'can_view_auction_prices' => $canViewAuctionPrices,
            ],
        ]);
    }

    public function similar(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->isPublished(), 404);

        $similar = Listing::published()
            ->where('id', '!=', $listing->id)
            ->whereHas('vehicle', fn ($q) => $q->where('make', $listing->vehicle->make))
            ->with(['vehicle', 'coverImage', 'auction'])
            ->take(6)
            ->get();

        $canViewAuctionPrices = $this->canViewAuctionPrices($request);
        $this->priceMasker->maskMany($similar, $canViewAuctionPrices);

        return response()->json([
            'data' => $similar,
            'meta' => [
                'can_view_auction_prices' => $canViewAuctionPrices,
            ],
        ]);
    }

    /**
     * Endpoint public mais verrouillé par middleware pour les enchères.
     * Les prix fixes / stock restent consultables publiquement.
     */
    public function pricing(Listing $listing): JsonResponse
    {
        abort_unless($listing->isPublished(), 404);

        $summary = $this->depositCalculator->summaryFromListing($listing);

        return response()->json([
            'listing_id' => $listing->id,
            'listing_type' => $listing->listing_type->value,
            'currency' => $listing->currency,
            'pricing' => $summary,
        ]);
    }

    private function canViewAuctionPrices(Request $request): bool
    {
        return (bool) ($request->user() ?? $request->user('sanctum'));
    }
}
