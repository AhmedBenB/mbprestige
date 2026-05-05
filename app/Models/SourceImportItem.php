<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceImportItem extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'source_import_id',
        'external_id',
        'status',
        'payload',
        'normalized_payload',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'normalized_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function sourceImport(): BelongsTo
    {
        return $this->belongsTo(SourceImport::class);
    }
}
