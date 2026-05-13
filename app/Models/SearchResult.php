<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchResult extends Model
{
    protected $fillable = [
        'customer_search_id',
        'search_run_id',
        'source_ref',
        'listing_url',
        'title',
        'make',
        'model',
        'price',
        'year',
        'fuel',
        'gearbox',
        'mileage',
        'color',
        'match_score',
        'match_status',
        'admin_summary',
        'review_notes',
        'reviewed_at',
        'shared_with_client_at',
        'shared_channel',
        'shared_note',
        'raw_payload',
    ];

    protected $casts = [
        'price' => 'float',
        'year' => 'integer',
        'mileage' => 'integer',
        'match_score' => 'integer',
        'reviewed_at' => 'datetime',
        'shared_with_client_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public const STATUS_CANDIDATE = 'candidate';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_SHARED = 'shared';

    public function search(): BelongsTo
    {
        return $this->belongsTo(CustomerSearch::class, 'customer_search_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SearchRun::class, 'search_run_id');
    }
}
