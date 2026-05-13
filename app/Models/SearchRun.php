<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchRun extends Model
{
    protected $fillable = [
        'customer_search_id',
        'source',
        'zone',
        'status',
        'query_payload',
        'result_count',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'query_payload' => 'array',
        'result_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function search(): BelongsTo
    {
        return $this->belongsTo(CustomerSearch::class, 'customer_search_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(SearchResult::class);
    }
}
