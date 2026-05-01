<?php

namespace App\Services\Auctions;

use App\Jobs\NotifyOutbidUsersJob;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceBidService
{
    public function __construct(
        private readonly AuctionStateResolver $stateResolver
    ) {}

    public function place(Listing $listing, User $user, float $amount): Bid
    {
        return DB::transaction(function () use ($listing, $user, $amount) {

            // Verrouillage pessimiste
            $listing = Listing::query()
                ->whereKey($listing->id)
                ->lockForUpdate()
                ->firstOrFail();

            $auction = Auction::query()
                ->where('listing_id', $listing->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Vérification éligibilité
            if (! $this->stateResolver->canBid($listing, $auction, $user)) {
                throw ValidationException::withMessages([
                    'bid' => 'Vous ne pouvez pas enchérir sur cette annonce.',
                ]);
            }

            // Vérification montant minimum
            $minimum = $this->getMinimumBid($listing, $auction);
            if ($amount < $minimum) {
                throw ValidationException::withMessages([
                    'amount' => "Le montant minimum est {$minimum} {$listing->currency}.",
                ]);
            }

            // Vérification limite organisation (trial)
            $this->checkOrganizationLimit($user, $listing);

            // Création de l'offre
            $bid = Bid::create([
                'listing_id'      => $listing->id,
                'user_id'         => $user->id,
                'organization_id' => $user->organization_id,
                'amount'          => $amount,
                'currency'        => $listing->currency,
                'status'          => 'leading',
                'bid_type'        => 'manual',
                'placed_at'       => now(),
            ]);

            // Rétrogradation des offres précédentes
            Bid::query()
                ->where('listing_id', $listing->id)
                ->where('id', '!=', $bid->id)
                ->where('status', 'leading')
                ->update(['status' => 'outbid']);

            // Mise à jour du listing
            $listing->update([
                'current_bid' => $amount,
                'bid_count'   => $listing->bid_count + 1,
            ]);

            // Soft close : prolonger si offre dans les dernières secondes
            $secondsRemaining = now()->diffInSeconds($auction->ends_at, false);
            if ($secondsRemaining <= $auction->extend_if_bid_in_last_seconds && $secondsRemaining > 0) {
                $newEnd = $auction->ends_at->copy()->addSeconds($auction->soft_close_seconds);
                $auction->update(['ends_at' => $newEnd]);
                $listing->update(['ends_at' => $newEnd]);
            }

            // Notifications asynchrones
            dispatch(new NotifyOutbidUsersJob($listing->id, $bid->id));

            return $bid;
        });
    }

    public function cancel(Bid $bid, User $user): void
    {
        if (! $bid->isCancellable()) {
            throw ValidationException::withMessages([
                'bid' => 'Cette offre ne peut pas être annulée.',
            ]);
        }

        if ($bid->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'bid' => 'Vous ne pouvez annuler que vos propres offres.',
            ]);
        }

        DB::transaction(function () use ($bid) {
            $bid->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Si c'était la meilleure offre, recalculer
            if ($bid->isLeading()) {
                $nextBid = Bid::query()
                    ->where('listing_id', $bid->listing_id)
                    ->where('id', '!=', $bid->id)
                    ->where('status', 'outbid')
                    ->orderByDesc('amount')
                    ->first();

                if ($nextBid) {
                    $nextBid->update(['status' => 'leading']);
                    $bid->listing->update(['current_bid' => $nextBid->amount]);
                } else {
                    $bid->listing->update(['current_bid' => null, 'bid_count' => max(0, $bid->listing->bid_count - 1)]);
                }
            }
        });
    }

    private function getMinimumBid(Listing $listing, Auction $auction): float
    {
        $current = $listing->current_bid ?? $listing->starting_price ?? 0;
        return $current + $auction->minimum_increment;
    }

    private function checkOrganizationLimit(User $user, Listing $listing): void
    {
        $org = $user->organization;
        if (! $org) return;

        $activeBids = Bid::query()
            ->where('organization_id', $org->id)
            ->whereIn('status', ['pending', 'leading', 'won_pending_validation'])
            ->count();

        if ($activeBids >= $org->maxActiveBids()) {
            throw ValidationException::withMessages([
                'bid' => "Limite d'offres actives atteinte ({$org->maxActiveBids()}) pour votre niveau {$org->user_tier}.",
            ]);
        }
    }
}
