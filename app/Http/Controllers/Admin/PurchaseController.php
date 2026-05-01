<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PublicationStatusEnum;
use App\Enums\PurchaseStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Purchase;
use App\Services\Purchases\PurchaseReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseReservationService $purchaseService
    ) {}

    public function index(Request $request): View
    {
        $purchases = Purchase::query()
            ->with(['user', 'listing', 'payment'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, function ($q) use ($request) {
                $term = trim((string) $request->search);
                $q->where(function ($query) use ($term) {
                    $query->where('id', $term)
                        ->orWhereHas('listing', fn ($sq) => $sq->where('title', 'like', "%{$term}%"))
                        ->orWhereHas('user', fn ($sq) => $sq->where('email', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.purchases.index', compact('purchases'));
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load(['user.organization', 'listing.vehicle', 'payment', 'payments']);

        return view('admin.purchases.show', compact('purchase'));
    }

    public function cancel(Purchase $purchase): RedirectResponse
    {
        if (in_array($purchase->status, [PurchaseStatusEnum::Cancelled, PurchaseStatusEnum::Completed], true)) {
            return back()->withErrors(['purchase' => 'Cette réservation ne peut plus être annulée.']);
        }

        DB::transaction(function () use ($purchase) {
            $lockedPurchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            $lockedPurchase->update([
                'status' => PurchaseStatusEnum::Cancelled,
                'expires_at' => null,
            ]);

            $listing = Listing::query()->whereKey($lockedPurchase->listing_id)->lockForUpdate()->first();
            if ($listing && in_array($listing->publication_status, [
                PublicationStatusEnum::Reserved,
                PublicationStatusEnum::SoldPendingPayment,
                PublicationStatusEnum::SoldPendingBalance,
            ], true)) {
                $listing->update(['publication_status' => PublicationStatusEnum::Published]);
            }
        });

        Log::warning('Admin cancelled reservation', [
            'purchase_id' => $purchase->id,
            'admin_id' => auth()->id(),
        ]);

        return back()->with('success', 'Réservation annulée.');
    }

    public function markDepositPaid(Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchaseService->markDepositPaid($purchase, $purchase->payment);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        Log::info('Admin marked deposit paid', [
            'purchase_id' => $purchase->id,
            'admin_id' => auth()->id(),
        ]);

        return back()->with('success', 'Acompte marqué comme payé.');
    }

    public function markCompleted(Purchase $purchase): RedirectResponse
    {
        DB::transaction(function () use ($purchase) {
            $lockedPurchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $listing = Listing::query()->whereKey($lockedPurchase->listing_id)->lockForUpdate()->firstOrFail();

            $lockedPurchase->update([
                'status' => PurchaseStatusEnum::Completed,
                'deposit_paid_at' => $lockedPurchase->deposit_paid_at ?? now(),
                'expires_at' => null,
            ]);

            $listing->update([
                'publication_status' => PublicationStatusEnum::Paid,
            ]);
        });

        Log::info('Admin marked reservation completed', [
            'purchase_id' => $purchase->id,
            'admin_id' => auth()->id(),
        ]);

        return back()->with('success', 'Réservation finalisée (solde payé).');
    }
}
