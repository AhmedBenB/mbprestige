<?php

namespace App\Services\Purchases;

use App\Enums\PublicationStatusEnum;
use App\Enums\PurchaseStatusEnum;
use App\Enums\ListingTypeEnum;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PurchaseReservationService
{
    public function reserveBuyNow(Listing $listing, User $user): Purchase
    {
        return DB::transaction(function () use ($listing, $user) {
            $lockedListing = Listing::query()
                ->whereKey($listing->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedListing->listing_type, [
                ListingTypeEnum::AuctionOpen,
                ListingTypeEnum::AuctionBlind,
            ], true)) {
                throw ValidationException::withMessages([
                    'listing' => 'Achat immédiat indisponible pour une enchère active.',
                ]);
            }

            $this->expireStaleReservations($lockedListing->id);

            $activeReservation = Purchase::query()
                ->where('listing_id', $lockedListing->id)
                ->whereIn('status', [PurchaseStatusEnum::Reserved, PurchaseStatusEnum::DepositPending])
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($activeReservation) {
                if ($activeReservation->user_id !== $user->id) {
                    throw ValidationException::withMessages([
                        'listing' => 'Ce véhicule est déjà réservé par un autre client.',
                    ]);
                }

                if ($lockedListing->publication_status !== PublicationStatusEnum::Reserved) {
                    $lockedListing->update(['publication_status' => PublicationStatusEnum::Reserved]);
                }

                return $activeReservation;
            }

            if ($lockedListing->publication_status === PublicationStatusEnum::Reserved) {
                $lockedListing->update(['publication_status' => PublicationStatusEnum::Published]);
                $lockedListing->refresh();
            }

            if ($lockedListing->publication_status !== PublicationStatusEnum::Published) {
                throw ValidationException::withMessages([
                    'listing' => 'Ce véhicule n’est plus disponible.',
                ]);
            }

            $reservationMinutes = (int) config('payments.reservation_ttl_minutes', 30);
            $expiresAt = now()->addMinutes(max(1, $reservationMinutes));

            $purchase = Purchase::create([
                'user_id' => $user->id,
                'listing_id' => $lockedListing->id,
                'organization_id' => $user->organization_id,
                'status' => PurchaseStatusEnum::Reserved,
                'reserved_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            $lockedListing->update([
                'publication_status' => PublicationStatusEnum::Reserved,
            ]);

            Log::info('Reservation created', [
                'purchase_id' => $purchase->id,
                'listing_id' => $lockedListing->id,
                'user_id' => $user->id,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return $purchase;
        });
    }

    public function markDepositPending(Purchase $purchase): Purchase
    {
        if ($purchase->status === PurchaseStatusEnum::DepositPending) {
            return $purchase;
        }

        if ($purchase->status !== PurchaseStatusEnum::Reserved) {
            throw ValidationException::withMessages([
                'purchase' => 'Cette réservation ne peut plus passer en attente d’acompte.',
            ]);
        }

        $purchase->update([
            'status' => PurchaseStatusEnum::DepositPending,
        ]);

        return $purchase->fresh();
    }

    public function markDepositPaid(Purchase $purchase, ?Payment $payment = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $payment) {
            $lockedPurchase = Purchase::query()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPurchase->status === PurchaseStatusEnum::DepositPaid) {
                return $lockedPurchase;
            }

            $lockedPurchase->update([
                'status' => PurchaseStatusEnum::DepositPaid,
                'deposit_paid_at' => now(),
                'expires_at' => null,
                'payment_id' => $payment?->id ?? $lockedPurchase->payment_id,
            ]);

            Listing::query()
                ->whereKey($lockedPurchase->listing_id)
                ->lockForUpdate()
                ->firstOrFail()
                ->update([
                    'publication_status' => PublicationStatusEnum::SoldPendingBalance,
                ]);

            Log::info('Deposit paid for reservation', [
                'purchase_id' => $lockedPurchase->id,
                'listing_id' => $lockedPurchase->listing_id,
                'payment_id' => $payment?->id,
            ]);

            return $lockedPurchase->fresh();
        });
    }

    public function expirePurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $lockedPurchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->first();
            if (! $lockedPurchase) {
                return;
            }

            if (! in_array($lockedPurchase->status, [PurchaseStatusEnum::Reserved, PurchaseStatusEnum::DepositPending], true)) {
                return;
            }

            if ($lockedPurchase->expires_at && $lockedPurchase->expires_at->isFuture()) {
                return;
            }

            $lockedPurchase->update([
                'status' => PurchaseStatusEnum::Cancelled,
            ]);

            $listing = Listing::query()->whereKey($lockedPurchase->listing_id)->lockForUpdate()->first();
            if (! $listing) {
                return;
            }

            $otherActive = Purchase::query()
                ->where('listing_id', $listing->id)
                ->where('id', '!=', $lockedPurchase->id)
                ->whereIn('status', [PurchaseStatusEnum::Reserved, PurchaseStatusEnum::DepositPending])
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->exists();

            if (! $otherActive && $listing->publication_status === PublicationStatusEnum::Reserved) {
                $listing->update(['publication_status' => PublicationStatusEnum::Published]);
            }

            Log::warning('Reservation expired', [
                'purchase_id' => $lockedPurchase->id,
                'listing_id' => $lockedPurchase->listing_id,
            ]);
        });
    }

    private function expireStaleReservations(int $listingId): void
    {
        $expired = Purchase::query()
            ->where('listing_id', $listingId)
            ->whereIn('status', [PurchaseStatusEnum::Reserved, PurchaseStatusEnum::DepositPending])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $purchase) {
            $purchase->update(['status' => PurchaseStatusEnum::Cancelled]);
        }
    }
}
