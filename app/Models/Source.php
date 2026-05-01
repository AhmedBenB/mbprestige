<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Source extends Model
{
    protected $fillable = [
        'name',
        'type',
        'base_url',
        'auth_mode',
        'credentials_encrypted',
        'import_frequency_minutes',
        'is_active',
        'auto_approve',
        'last_sync_at',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_approve' => 'boolean',
        'last_sync_at' => 'datetime',
        'meta' => 'array',
    ];

    protected $hidden = ['credentials_encrypted'];

    public function imports(): HasMany
    {
        return $this->hasMany(SourceImport::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function lastImport(): HasOne
    {
        return $this->hasOne(SourceImport::class)->latestOfMany();
    }
}
