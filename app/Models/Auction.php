<?php

namespace App\Models;

use App\Enums\AuctionStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auction extends Model
{
    protected $fillable = [
        'listing_id', 'auction_mode', 'status',
        'starts_at', 'ends_at',
        'soft_close_seconds', 'extend_if_bid_in_last_seconds',
        'minimum_increment', 'reserve_price',
        'winner_bid_id', 'decision_status', 'decision_at',
    ];

    protected $casts = [
        'status' => AuctionStatusEnum::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'decision_at' => 'datetime',
        'minimum_increment' => 'decimal:2',
        'reserve_price' => 'decimal:2',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function winnerBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'winner_bid_id');
    }

    public function isLive(): bool
    {
        return now()->between($this->starts_at, $this->ends_at);
    }

    public function secondsRemaining(): int
    {
        return max(0, (int) now()->diffInSeconds($this->ends_at, false));
    }

    public function isEndingSoon(int $threshold = 3600): bool
    {
        return $this->isLive() && $this->secondsRemaining() <= $threshold;
    }
}
