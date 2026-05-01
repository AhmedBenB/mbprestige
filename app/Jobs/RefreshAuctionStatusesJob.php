<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshAuctionStatusesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Auction::query()
            ->where('status', 'scheduled')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->update(['status' => 'live']);

        Listing::query()
            ->whereIn('listing_type', ['auction_open', 'auction_blind'])
            ->where('publication_status', 'published')
            ->whereHas('auction', fn ($q) => $q->where('status', 'live'))
            ->update(['auction_status' => 'live']);

        Auction::query()
            ->where('status', 'live')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addHour())
            ->update(['status' => 'ending_soon']);

        Listing::query()
            ->whereHas('auction', fn ($q) => $q->where('status', 'ending_soon'))
            ->update(['auction_status' => 'ending_soon']);
    }
}
