<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;

class AppFavoriteController extends Controller
{
    public function index(): JsonResponse
    {
        $favorites = Favorite::query()
            ->where('user_id', auth()->id())
            ->with(['listing.vehicle', 'listing.coverImage', 'listing.auction'])
            ->latest()
            ->paginate(24);

        return response()->json($favorites);
    }

    public function store(Listing $listing): JsonResponse
    {
        $favorite = Favorite::firstOrCreate([
            'user_id' => auth()->id(),
            'listing_id' => $listing->id,
        ]);

        return response()->json([
            'message' => 'Ajouté aux favoris.',
            'data' => $favorite,
        ], 201);
    }

    public function destroy(Listing $listing): JsonResponse
    {
        Favorite::query()
            ->where('user_id', auth()->id())
            ->where('listing_id', $listing->id)
            ->delete();

        return response()->json(['message' => 'Retiré des favoris.']);
    }
}
