<?php

namespace App\Models;

use App\Enums\BidStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    protected $fillable = [
        'listing_id', 'user_id', 'organization_id',
        'amount', 'currency', 'status', 'bid_type',
        'placed_at', 'cancelled_at', 'meta_json',
    ];

    protected $casts = [
        'status' => BidStatusEnum::class,
        'placed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta_json' => 'array',
        'amount' => 'decimal:2',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isLeading(): bool
    {
        return $this->status === BidStatusEnum::Leading;
    }

    public function isCancellable(): bool
    {
        // En blind auction uniquement, et si encore active
        return in_array($this->status, [BidStatusEnum::Pending, BidStatusEnum::Leading])
            && $this->listing->listing_type->value === 'auction_blind';
    }
}
