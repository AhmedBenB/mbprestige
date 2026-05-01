<?php

namespace App\Jobs;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ArchiveExpiredListingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Listing::query()
            ->where('publication_status', 'published')
            ->whereIn('listing_type', ['fixed_price', 'partner_stock'])
            ->where('published_at', '<=', now()->subDays(90))
            ->update([
                'publication_status' => 'archived',
                'archived_at' => now(),
            ]);

        Listing::query()
            ->where('auction_status', 'not_awarded')
            ->where('ends_at', '<=', now()->subDays(30))
            ->update([
                'publication_status' => 'archived',
                'archived_at' => now(),
            ]);
    }
}
