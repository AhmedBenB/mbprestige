<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingImage extends Model
{
    protected $fillable = [
        'listing_id',
        'source_url',
        'local_path',
        'cdn_url',
        'width',
        'height',
        'checksum',
        'sort_order',
        'processing_status',
        'rights_status',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function url(): string
    {
        return $this->cdn_url ?? $this->source_url ?? '';
    }

    public function isReady(): bool
    {
        return $this->processing_status === 'ready';
    }
}
