<?php

namespace App\Services\Listings;

use App\Models\Listing;
use Illuminate\Support\Collection;

class AuctionPriceMasker
{
    /**
     * Masque les montants sensibles d'une enchère pour un visiteur non connecté.
     */
    public function maskListing(Listing $listing, bool $canViewAuctionPrices): Listing
    {
        $hideAuctionPricing = $listing->isAuction() && ! $canViewAuctionPrices;

        $listing->setAttribute('auction_price_hidden', $hideAuctionPricing);
        $listing->setAttribute('auction_price_visibility', $hideAuctionPricing ? 'login_required' : 'visible');

        if (! $hideAuctionPricing) {
            return $listing;
        }

        foreach ([
            'starting_price',
            'reserve_price',
            'current_bid',
            'buy_now_price',
            'estimate_price',
            'minimum_increment',
        ] as $field) {
            $listing->setAttribute($field, null);
        }

        return $listing;
    }

    /**
     * @param iterable<Listing> $listings
     */
    public function maskMany(iterable $listings, bool $canViewAuctionPrices): void
    {
        if ($listings instanceof Collection) {
            $listings->each(fn (Listing $listing) => $this->maskListing($listing, $canViewAuctionPrices));
            return;
        }

        foreach ($listings as $listing) {
            if ($listing instanceof Listing) {
                $this->maskListing($listing, $canViewAuctionPrices);
            }
        }
    }
}
