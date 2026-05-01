<?php

namespace App\Services\Payment;

use App\Models\Listing;

class DepositCalculator
{
    public const DEFAULT_DEPOSIT_RATE = 0.10;

    public function summaryFromListing(Listing $listing, ?float $depositRate = null): array
    {
        $vehicleAmount = (float) ($listing->buy_now_price ?? $listing->current_bid ?? $listing->starting_price ?? 0);

        if ($vehicleAmount <= 0) {
            abort(422, 'Aucun montant exploitable pour ce véhicule.');
        }

        return $this->summaryFromAmount($vehicleAmount, $depositRate);
    }

    public function summaryFromAmount(float $vehicleAmount, ?float $depositRate = null): array
    {
        $rate = $depositRate ?? $this->defaultDepositRate();
        $rate = max(0, min(1, $rate));

        $commission = max(150, round($vehicleAmount * 0.025, 2));
        $total = round($vehicleAmount + $commission, 2);

        $depositNow = round($total * $rate, 2);
        $remainingAfterDeposit = round($total - $depositNow, 2);

        return [
            'vehicle_amount' => $vehicleAmount,
            'commission' => $commission,
            'total' => $total,
            'deposit_rate' => $rate,
            'deposit_now' => $depositNow,
            'remaining_after_deposit' => $remainingAfterDeposit,
        ];
    }

    private function defaultDepositRate(): float
    {
        $percentage = (float) config('payments.deposit_percentage', 10);
        $percentage = max(0, min(100, $percentage));

        return $percentage / 100;
    }
}
