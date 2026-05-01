<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatusEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\PublicationStatusEnum;
use App\Enums\PurchaseStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Purchase;
use App\Services\Purchases\PurchaseReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PurchaseReservationService $purchaseService
    ) {}

    public function index(Request $request): View
    {
        $payments = Payment::query()
            ->with(['user', 'listing', 'purchase'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->search, function ($q) use ($request) {
                $term = trim((string) $request->search);
                $q->where(function ($query) use ($term) {
                    $query->where('id', $term)
                        ->orWhere('provider_session_id', 'like', "%{$term}%")
                        ->orWhere('provider_payment_intent_id', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($sq) => $sq->where('email', 'like', "%{$term}%"))
                        ->orWhereHas('listing', fn ($sq) => $sq->where('title', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.payments.index', compact('payments'));
    }

    public function show(Payment $payment): View
    {
        $payment->load(['user.organization', 'listing.vehicle', 'purchase']);

        return view('admin.payments.show', compact('payment'));
    }

    public function markPaid(Payment $payment): RedirectResponse
    {
        DB::transaction(function () use ($payment) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedPayment->update([
                'status' => PaymentStatusEnum::Paid,
                'paid_at' => $lockedPayment->paid_at ?? now(),
            ]);

            $this->syncPurchaseAndListingForPaidPayment($lockedPayment);
        });

        Log::info('Admin marked payment paid', [
            'payment_id' => $payment->id,
            'admin_id' => auth()->id(),
        ]);

        return back()->with('success', 'Paiement marqué comme payé.');
    }

    public function markFailed(Payment $payment): RedirectResponse
    {
        $payment->update([
            'status' => PaymentStatusEnum::Failed,
            'metadata' => array_merge($payment->metadata ?? [], [
                'manual_action' => 'marked_failed',
                'manual_admin_id' => auth()->id(),
            ]),
        ]);

        Log::warning('Admin marked payment failed', [
            'payment_id' => $payment->id,
            'admin_id' => auth()->id(),
        ]);

        return back()->with('success', 'Paiement marqué en échec.');
    }

    public function markRefunded(Payment $payment): RedirectResponse
    {
        $payment->update([
            'status' => PaymentStatusEnum::Refunded,
            'metadata' => array_merge($payment->metadata ?? [], [
                'manual_action' => 'marked_refunded',
                'manual_admin_id' => auth()->id(),
            ]),
        ]);

        Log::warning('Admin marked payment refunded', [
            'payment_id' => $payment->id,
            'admin_id' => auth()->id(),
        ]);

        return back()->with('success', 'Paiement marqué remboursé.');
    }

    private function syncPurchaseAndListingForPaidPayment(Payment $payment): void
    {
        if (! $payment->purchase_id) {
            return;
        }

        $purchase = Purchase::query()->whereKey($payment->purchase_id)->lockForUpdate()->first();
        if (! $purchase) {
            return;
        }

        if ($payment->type === PaymentTypeEnum::Deposit) {
            $this->purchaseService->markDepositPaid($purchase, $payment);
            return;
        }

        $purchase->update([
            'status' => PurchaseStatusEnum::Completed,
            'deposit_paid_at' => $purchase->deposit_paid_at ?? now(),
            'expires_at' => null,
            'payment_id' => $payment->id,
        ]);

        Listing::query()
            ->whereKey($purchase->listing_id)
            ->lockForUpdate()
            ->first()?->update([
                'publication_status' => PublicationStatusEnum::Paid,
            ]);
    }
}
