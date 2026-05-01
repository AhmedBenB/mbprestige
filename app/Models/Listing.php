<?php

namespace App\Models;

use App\Enums\AuctionStatusEnum;
use App\Enums\ListingTypeEnum;
use App\Enums\PublicationStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Listing extends Model
{
    protected $fillable = [
        'source_id', 'source_external_id', 'vehicle_id', 'organization_id',
        'listing_type', 'publication_status', 'auction_status',
        'title', 'slug', 'short_description', 'long_description',
        'currency', 'starting_price', 'reserve_price', 'buy_now_price',
        'current_bid', 'estimate_price', 'minimum_increment', 'bid_count',
        'starts_at', 'ends_at', 'seller_decision_deadline_at',
        'published_at', 'archived_at', 'last_source_sync_at', 'source_payload_hash',
        'vat_deductible', 'is_featured',
    ];

    protected $casts = [
        'listing_type' => ListingTypeEnum::class,
        'publication_status' => PublicationStatusEnum::class,
        'auction_status' => AuctionStatusEnum::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'seller_decision_deadline_at' => 'datetime',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'last_source_sync_at' => 'datetime',
        'vat_deductible' => 'boolean',
        'is_featured' => 'boolean',
        'starting_price' => 'decimal:2',
        'reserve_price' => 'decimal:2',
        'buy_now_price' => 'decimal:2',
        'current_bid' => 'decimal:2',
        'estimate_price' => 'decimal:2',
        'minimum_increment' => 'decimal:2',
    ];

    // ── Relations ──────────────────────────────────────────────

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function auction(): HasOne
    {
        return $this->hasOne(Auction::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(ListingImage::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ListingDocument::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ListingAttribute::class)->orderBy('sort_order');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class)->latest('amount');
    }

    public function leadingBid(): HasOne
    {
        return $this->hasOne(Bid::class)->where('status', 'leading')->orderByDesc('amount');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('publication_status', PublicationStatusEnum::Published);
    }

    public function scopeAuctions(Builder $query): Builder
    {
        return $query->whereIn('listing_type', [
            ListingTypeEnum::AuctionOpen->value,
            ListingTypeEnum::AuctionBlind->value,
        ]);
    }

    public function scopeFixedPrices(Builder $query): Builder
    {
        return $query->where('listing_type', ListingTypeEnum::FixedPrice);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->published()
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function scopeEndingSoon(Builder $query, int $hours = 24): Builder
    {
        return $query->live()
            ->where('ends_at', '<=', now()->addHours($hours));
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    // ── Helpers ──────────────────────────────────────────────

    public function isAuction(): bool
    {
        return $this->listing_type->isAuction();
    }

    public function isPublished(): bool
    {
        return $this->publication_status === PublicationStatusEnum::Published;
    }

    public function minimumBid(): float
    {
        return ($this->current_bid ?? $this->starting_price ?? 0) + $this->minimum_increment;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
