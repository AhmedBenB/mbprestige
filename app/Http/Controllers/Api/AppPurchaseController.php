<?php

namespace App\Http\Controllers\Api;

use App\Enums\PublicationStatusEnum;
use App\Enums\PurchaseStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Purchase;
use App\Services\Payment\DepositCalculator;
use App\Services\Payment\StripePaymentService;
use App\Services\Purchases\PurchaseReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppPurchaseController extends Controller
{
    public function __construct(
        private readonly DepositCalculator $depositCalculator,
        private readonly StripePaymentService $stripe,
        private readonly PurchaseReservationService $purchaseService
    ) {}

    public function buyNow(Request $request, Listing $listing): JsonResponse
    {
        abort_unless(in_array($listing->publication_status, [
            PublicationStatusEnum::Published,
            PublicationStatusEnum::Reserved,
        ], true), 404);
        abort_unless($listing->buy_now_price, 422, 'Pas de prix fixe disponible.');

        try {
            $purchase = $this->purchaseService->reserveBuyNow($listing, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Véhicule réservé. Réglez maintenant uniquement l’acompte.',
            'listing_id' => $listing->id,
            'purchase_id' => $purchase->id,
            'reservation_expires_at' => $purchase->expires_at?->toIso8601String(),
            'publication_status' => $listing->fresh()->publication_status->value,
            'pricing' => $this->depositCalculator->summaryFromListing($listing),
        ]);
    }

    public function pricing(Request $request, Listing $listing): JsonResponse
    {
        try {
            $purchase = $this->findOrCreatePurchase($listing, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json([
            'listing_id' => $listing->id,
            'purchase_id' => $purchase->id,
            'currency' => $listing->currency,
            'reservation_expires_at' => $purchase->expires_at?->toIso8601String(),
            'pricing' => $this->depositCalculator->summaryFromListing($listing),
        ]);
    }

    public function depositIntent(Request $request, Listing $listing): JsonResponse
    {
        try {
            $purchase = $this->findOrCreatePurchase($listing, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        $summary = $this->depositCalculator->summaryFromListing($listing);
        try {
            $intent = $this->stripe->createDepositPaymentIntent(
                $listing,
                $request->user(),
                $purchase,
                $summary['deposit_now']
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Intent Stripe créé pour l’acompte.',
            'purchase_id' => $purchase->id,
            'pricing' => $summary,
            'payment' => $intent,
        ]);
    }

    public function depositCheckout(Request $request, Listing $listing): JsonResponse
    {
        try {
            $purchase = $this->findOrCreatePurchase($listing, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        $summary = $this->depositCalculator->summaryFromListing($listing);
        try {
            $session = $this->stripe->createDepositCheckoutSession(
                $listing,
                $request->user(),
                $purchase,
                $summary['deposit_now']
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Session Stripe Checkout créée pour l’acompte.',
            'purchase_id' => $purchase->id,
            'pricing' => $summary,
            'checkout_url' => $session->url,
            'checkout_id' => $session->id,
        ]);
    }

    private function findOrCreatePurchase(Listing $listing, $user): Purchase
    {
        $purchase = Purchase::query()
            ->where('listing_id', $listing->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [
                PurchaseStatusEnum::Reserved,
                PurchaseStatusEnum::DepositPending,
                PurchaseStatusEnum::DepositPaid,
            ])
            ->latest('id')
            ->first();

        if ($purchase) {
            return $purchase;
        }

        return $this->purchaseService->reserveBuyNow($listing, $user);
    }
}
