<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResolveEndedAuctionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Auction::query()
            ->whereIn('status', ['live', 'ending_soon'])
            ->where('ends_at', '<=', now())
            ->each(function (Auction $auction) {
                $auction->update([
                    'status' => 'ended_waiting_validation',
                ]);

                $auction->listing->update([
                    'auction_status' => 'ended_waiting_validation',
                ]);

                $leadingBid = Bid::query()
                    ->where('listing_id', $auction->listing_id)
                    ->where('status', 'leading')
                    ->orderByDesc('amount')
                    ->first();

                if ($leadingBid) {
                    $leadingBid->update(['status' => 'won_pending_validation']);
                }
            });
    }
}
