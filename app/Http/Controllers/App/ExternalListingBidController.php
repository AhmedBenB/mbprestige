<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ExternalListing;
use App\Models\ExternalListingBid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExternalListingBidController extends Controller
{
    public function store(Request $request, ExternalListing $listing): RedirectResponse
    {
        $isAuction = str_starts_with((string) $listing->listing_type, 'auction_');
        $isExpired = ((string) $listing->status === ExternalListing::STATUS_EXPIRED)
            || ($listing->auction_end_at && $listing->auction_end_at->isPast());

        if (!$isAuction || $isExpired) {
            return back()->withErrors([
                'bid_amount' => "Cette annonce n'accepte plus d'encheres.",
            ]);
        }

        $payload = $request->validate([
            'bid_amount' => ['required', 'numeric', 'min:1'],
        ], [
            'bid_amount.required' => "Saisis un montant d'enchere.",
            'bid_amount.numeric' => 'Le montant doit etre un nombre.',
            'bid_amount.min' => 'Le montant doit etre superieur a 0.',
        ]);

        ExternalListingBid::query()->create([
            'external_listing_id' => $listing->id,
            'user_id' => (int) $request->user()->id,
            'organization_id' => $request->user()->organization_id,
            'amount' => (float) $payload['bid_amount'],
            'currency' => (string) ($listing->currency ?: 'EUR'),
            'status' => 'pending',
            'placed_at' => now(),
            'meta_json' => [
                'source' => 'website',
            ],
        ]);

        return back()->with('success', 'Enchere enregistree. Elle apparait desormais dans l\'espace admin.');
    }
}
