<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingDocument extends Model
{
    protected $fillable = [
        'listing_id',
        'type',
        'file_path',
        'visibility',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }
}
