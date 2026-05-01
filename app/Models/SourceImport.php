<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceImport extends Model
{
    protected $fillable = [
        'source_id',
        'started_at',
        'finished_at',
        'status',
        'items_found',
        'items_created',
        'items_updated',
        'items_skipped',
        'items_failed',
        'raw_log',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SourceImportItem::class);
    }

    public function duration(): ?int
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->finished_at);
    }
}
