<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExternalListing extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY_FOR_REVIEW = 'ready_for_review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DO_NOT_PUBLISH = 'do_not_publish';

    protected $fillable = [
        'source_id',
        'external_id',
        'title',
        'slug',
        'listing_url',
        'listing_type',
        'source_status',
        'status',
        'currency',
        'price_visible',
        'price_amount',
        'auction_end_at',
        'make',
        'model',
        'year',
        'mileage',
        'fuel',
        'transmission',
        'color',
        'country',
        'location',
        'images',
        'technical_data',
        'equipment',
        'source_payload',
        'source_created_at',
        'source_updated_at',
        'last_seen_at',
        'published_at',
        'views_count',
    ];

    protected $casts = [
        'price_visible' => 'boolean',
        'price_amount' => 'float',
        'auction_end_at' => 'datetime',
        'images' => 'array',
        'technical_data' => 'array',
        'equipment' => 'array',
        'source_payload' => 'array',
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'published_at' => 'datetime',
        'views_count' => 'integer',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ListingDocument::class);
    }

    public function priceEstimates(): HasMany
    {
        return $this->hasMany(ListingPriceEstimate::class);
    }

    public function latestPriceEstimate(): HasOne
    {
        return $this->hasOne(ListingPriceEstimate::class)->latestOfMany();
    }

    public function similarities(): HasMany
    {
        return $this->hasMany(ListingSimilarity::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(ExternalListingBid::class);
    }
}
