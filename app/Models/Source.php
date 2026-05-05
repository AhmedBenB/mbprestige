<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    public const CODE_ECARSTRADE = 'ecarstrade';

    protected $fillable = [
        'code',
        'name',
        'type',
        'base_url',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function imports(): HasMany
    {
        return $this->hasMany(SourceImport::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(ExternalListing::class);
    }
}
