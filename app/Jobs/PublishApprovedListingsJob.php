<?php

namespace App\Jobs;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishApprovedListingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Listing::query()
            ->where('publication_status', 'approved')
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->each(function (Listing $listing) {
                $listing->update([
                    'publication_status' => 'published',
                    'published_at' => $listing->published_at ?? now(),
                    'auction_status' => $listing->isAuction() ? 'scheduled' : null,
                ]);
            });
    }
}
