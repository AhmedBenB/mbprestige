<?php

namespace App\Services\Auctions;

use App\Enums\AuctionStatusEnum;
use App\Models\Auction;
use App\Models\Listing;
use App\Models\User;

class AuctionStateResolver
{
    public function resolve(Listing $listing): string
    {
        $auction = $listing->auction;

        if (! $auction) {
            return 'none';
        }

        if ($auction->status === AuctionStatusEnum::Cancelled) {
            return 'cancelled';
        }

        $now = now();

        if ($auction->starts_at && $now->lt($auction->starts_at)) {
            return 'scheduled';
        }

        if ($auction->ends_at && $now->lt($auction->ends_at)) {
            $secondsLeft = $now->diffInSeconds($auction->ends_at, false);
            return $secondsLeft <= 3600 ? 'ending_soon' : 'live';
        }

        if ($auction->decision_at) {
            return match ($auction->decision_status) {
                'winner_selected' => 'winner_selected',
                'not_awarded'     => 'not_awarded',
                default           => 'ended_waiting_validation',
            };
        }

        if ($auction->ends_at && $now->gte($auction->ends_at)) {
            return 'ended_waiting_validation';
        }

        return 'ended_waiting_validation';
    }

    public function canBid(Listing $listing, Auction $auction, User $user): bool
    {
        $state = $this->resolve($listing);

        if (! in_array($state, ['live', 'ending_soon'])) {
            return false;
        }

        if (! $listing->isPublished()) {
            return false;
        }

        // Vérifier le statut de l'organisation
        $org = $user->organization;
        if (! $org || $org->status !== 'active') {
            return false;
        }

        return true;
    }

    public function canEditBid(Listing $listing, User $user): bool
    {
        // Uniquement en blind auction, et si encore live
        if ($listing->listing_type->value !== 'auction_blind') {
            return false;
        }

        $state = $this->resolve($listing);
        return in_array($state, ['live', 'ending_soon', 'scheduled']);
    }

    public function canCancelBid(Listing $listing, User $user): bool
    {
        return $this->canEditBid($listing, $user);
    }

    public function toFrontPayload(Listing $listing, ?User $user = null): array
    {
        $auction = $listing->auction;
        $state = $this->resolve($listing);

        return [
            'server_time_utc'                  => now()->utc()->toIso8601String(),
            'auction_status'                   => $state,
            'starts_at_utc'                    => $auction?->starts_at?->utc()->toIso8601String(),
            'ends_at_utc'                      => $auction?->ends_at?->utc()->toIso8601String(),
            'seller_decision_deadline_at_utc'  => $listing->seller_decision_deadline_at?->utc()->toIso8601String(),
            'soft_close_enabled'               => true,
            'soft_close_window_seconds'        => $auction?->soft_close_seconds ?? 120,
            'can_bid'                          => $user ? $this->canBid($listing, $auction, $user) : false,
            'can_edit_bid'                     => $user ? $this->canEditBid($listing, $user) : false,
            'can_cancel_bid'                   => $user ? $this->canCancelBid($listing, $user) : false,
        ];
    }
}
