<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Auctions\AuctionStateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionStateController extends Controller
{
    public function __construct(private readonly AuctionStateResolver $resolver) {}

    public function show(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->isPublished(), 404);
        abort_unless($listing->isAuction(), 404);

        $listing->load('auction');

        return response()->json([
            'listing_id' => $listing->id,
            'state' => $this->resolver->resolve($listing),
            'payload' => $this->resolver->toFrontPayload($listing, $request->user() ?? $request->user('sanctum')),
        ]);
    }
}
