<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\PlaceBidRequest;
use App\Models\Bid;
use App\Models\Listing;
use App\Services\Auctions\PlaceBidService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BidController extends Controller
{
    public function __construct(private readonly PlaceBidService $bidService) {}

    public function index(): View
    {
        $bids = auth()->user()
            ->bids()
            ->with(['listing.vehicle', 'listing.coverImage'])
            ->latest('placed_at')
            ->paginate(20);

        return view('app.bids.index', compact('bids'));
    }

    public function store(PlaceBidRequest $request, Listing $listing): RedirectResponse
    {
        try {
            $bid = $this->bidService->place($listing, auth()->user(), (float) $request->amount);

            return back()->with('success', "Votre offre de {$bid->amount} {$bid->currency} a été enregistrée.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    public function update(PlaceBidRequest $request, Bid $bid): RedirectResponse
    {
        if ($bid->user_id !== auth()->id()) {
            abort(403);
        }

        if (! $bid->isCancellable()) {
            return back()->withErrors(['bid' => 'Cette offre ne peut plus être modifiée.']);
        }

        $bid->update(['amount' => (float) $request->amount]);

        return back()->with('success', "Votre offre a été mise à jour : {$bid->fresh()->amount} {$bid->currency}.");
    }

    public function destroy(Bid $bid): RedirectResponse
    {
        try {
            $this->bidService->cancel($bid, auth()->user());
            return back()->with('success', 'Offre annulée.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }
}
