<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceImportItem extends Model
{
    protected $fillable = [
        'source_import_id',
        'external_id',
        'status',
        'raw_payload',
        'error_message',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    public function sourceImport(): BelongsTo
    {
        return $this->belongsTo(SourceImport::class);
    }
}
