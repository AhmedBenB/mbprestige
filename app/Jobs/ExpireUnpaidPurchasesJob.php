<?php

namespace App\Jobs;

use App\Enums\PurchaseStatusEnum;
use App\Models\Purchase;
use App\Services\Purchases\PurchaseReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireUnpaidPurchasesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PurchaseReservationService $purchaseService): void
    {
        $expiredPurchases = Purchase::query()
            ->whereIn('status', [PurchaseStatusEnum::Reserved, PurchaseStatusEnum::DepositPending])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredPurchases as $purchase) {
            $purchaseService->expirePurchase($purchase);
        }

        if ($expiredPurchases->isNotEmpty()) {
            Log::warning('Expired unpaid purchases processed', [
                'count' => $expiredPurchases->count(),
            ]);
        }
    }
}
