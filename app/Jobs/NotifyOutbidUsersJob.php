<?php

namespace App\Jobs;

use App\Models\Bid;
use App\Models\Listing;
use App\Notifications\OutbidNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyOutbidUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $listingId,
        public readonly int $newBidId
    ) {}

    public function handle(): void
    {
        $outbidBids = Bid::query()
            ->where('listing_id', $this->listingId)
            ->where('status', 'outbid')
            ->where('id', '!=', $this->newBidId)
            ->with('user')
            ->get()
            ->unique('user_id');

        $listing = Listing::find($this->listingId);

        foreach ($outbidBids as $bid) {
            if ($bid->user) {
                $bid->user->notify(new OutbidNotification($listing, $bid));
            }
        }
    }
}
