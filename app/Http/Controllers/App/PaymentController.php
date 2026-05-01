<?php

namespace App\Http\Controllers\App;

use App\Enums\PurchaseStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Purchase;
use App\Services\Payment\DepositCalculator;
use App\Services\Payment\StripePaymentService;
use App\Services\Purchases\PurchaseReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly StripePaymentService $stripe,
        private readonly DepositCalculator $depositCalculator,
        private readonly PurchaseReservationService $purchaseService
    ) {}

    /**
     * Affiche la page de paiement : acompte uniquement.
     */
    public function show(Listing $listing): View
    {
        try {
            $purchase = $this->findOrCreatePurchase($listing, request()->user());
        } catch (ValidationException $e) {
            abort(409, collect($e->errors())->flatten()->first() ?? 'Réservation indisponible.');
        }

        $summary = $this->depositCalculator->summaryFromListing($listing);

        return view('public.payment.checkout', [
            'listing' => $listing,
            'purchase' => $purchase,
            'summary' => $summary,
        ]);
    }

    /**
     * Crée une session Stripe Checkout pour l'acompte.
     */
    public function createCheckout(Request $request, Listing $listing): RedirectResponse
    {
        try {
            $purchase = $this->findOrCreatePurchase($listing, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $summary = $this->depositCalculator->summaryFromListing($listing);

        try {
            $session = $this->stripe->createDepositCheckoutSession(
                $listing,
                $request->user(),
                $purchase,
                $summary['deposit_now']
            );
            return redirect($session->url);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur de paiement : ' . $e->getMessage());
        }
    }

    /**
     * Crée un PaymentIntent pour l'acompte (intégration JS).
     */
    public function createIntent(Request $request, Listing $listing): JsonResponse
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
            return response()->json([
                'purchase_id' => $purchase->id,
                'pricing' => $summary,
                'payment' => $intent,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Page de succès fiable :
     * - affichée uniquement si le paiement est bien payé
     * - sinon redirige vers la page "en attente"
     */
    public function success(Request $request, Listing $listing): View
    {
        $sessionId = (string) $request->query('session_id', '');

        $purchase = Purchase::query()
            ->where('listing_id', $listing->id)
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        $payment = null;
        if ($sessionId !== '') {
            $payment = Payment::query()
                ->where('provider', 'stripe')
                ->where('provider_session_id', $sessionId)
                ->where('listing_id', $listing->id)
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->first();
        } elseif ($purchase) {
            $payment = Payment::query()
                ->where('purchase_id', $purchase->id)
                ->latest('id')
                ->first();
        }

        if (! $payment || $payment->status !== PaymentStatusEnum::Paid) {
            return $this->pending($request, $listing);
        }

        $summary = $this->depositCalculator->summaryFromListing($listing);

        return view('public.payment.success', compact('listing', 'purchase', 'payment', 'summary'));
    }

    /**
     * Page annulation Stripe.
     */
    public function cancel(Request $request, Listing $listing): View
    {
        $purchase = Purchase::query()
            ->where('listing_id', $listing->id)
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        return view('public.payment.cancel', compact('listing', 'purchase'));
    }

    /**
     * Page d'attente de confirmation (webhook).
     */
    public function pending(Request $request, Listing $listing): View
    {
        $purchase = Purchase::query()
            ->where('listing_id', $listing->id)
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        $payment = null;
        if ($purchase) {
            $payment = Payment::query()
                ->where('purchase_id', $purchase->id)
                ->latest('id')
                ->first();
        }

        return view('public.payment.pending', compact('listing', 'purchase', 'payment'));
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
