<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(): View
    {
        $favorites = Favorite::query()
            ->where('user_id', auth()->id())
            ->with(['listing.vehicle', 'listing.coverImage', 'listing.auction'])
            ->latest()
            ->paginate(24);

        return view('app.favorites.index', compact('favorites'));
    }

    public function store(Listing $listing): RedirectResponse
    {
        Favorite::firstOrCreate([
            'user_id' => auth()->id(),
            'listing_id' => $listing->id,
        ]);

        return back()->with('success', 'Ajouté aux favoris.');
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        Favorite::where('user_id', auth()->id())
            ->where('listing_id', $listing->id)
            ->delete();

        return back()->with('success', 'Retiré des favoris.');
    }
}
