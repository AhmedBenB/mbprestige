<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalListingBid extends Model
{
    protected $fillable = [
        'external_listing_id',
        'user_id',
        'organization_id',
        'amount',
        'currency',
        'status',
        'placed_at',
        'meta_json',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'placed_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ExternalListing::class, 'external_listing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
