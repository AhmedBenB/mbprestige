<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingPriceEstimate extends Model
{
    protected $fillable = [
        'external_listing_id',
        'estimated_price_min',
        'estimated_price_max',
        'estimated_price_confidence',
        'confidence_label',
        'estimated_price_reason',
        'sample_size',
        'meta',
    ];

    protected $casts = [
        'estimated_price_min' => 'float',
        'estimated_price_max' => 'float',
        'estimated_price_confidence' => 'float',
        'sample_size' => 'integer',
        'meta' => 'array',
    ];

    public function externalListing(): BelongsTo
    {
        return $this->belongsTo(ExternalListing::class);
    }
}
