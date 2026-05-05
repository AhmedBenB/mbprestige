<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingSimilarity extends Model
{
    protected $fillable = [
        'external_listing_id',
        'similar_external_listing_id',
        'score',
        'score_breakdown',
    ];

    protected $casts = [
        'score' => 'integer',
        'score_breakdown' => 'array',
    ];

    public function externalListing(): BelongsTo
    {
        return $this->belongsTo(ExternalListing::class);
    }

    public function similarListing(): BelongsTo
    {
        return $this->belongsTo(ExternalListing::class, 'similar_external_listing_id');
    }
}
