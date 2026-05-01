<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Listing;
use App\Services\Auctions\PlaceBidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppBidController extends Controller
{
    public function __construct(private readonly PlaceBidService $bidService) {}

    public function index(): JsonResponse
    {
        $bids = Bid::query()
            ->where('user_id', auth()->id())
            ->with(['listing.vehicle', 'listing.coverImage'])
            ->latest('placed_at')
            ->paginate(20);

        return response()->json($bids);
    }

    public function store(Request $request, Listing $listing): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            $bid = $this->bidService->place($listing, auth()->user(), (float) $data['amount']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Offre enregistrée.',
            'data' => $bid->fresh(),
        ], 201);
    }

    public function update(Request $request, Bid $bid): JsonResponse
    {
        abort_unless($bid->user_id === auth()->id(), 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            $this->bidService->cancel($bid, auth()->user());
            $newBid = $this->bidService->place($bid->listing, auth()->user(), (float) $data['amount']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Offre mise à jour.',
            'data' => $newBid,
        ]);
    }

    public function destroy(Bid $bid): JsonResponse
    {
        abort_unless($bid->user_id === auth()->id(), 403);

        try {
            $this->bidService->cancel($bid, auth()->user());
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(['message' => 'Offre annulée.']);
    }
}
