<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAssociationCode extends Model
{
    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'code',
        'label',
        'max_uses',
        'use_count',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'max_uses' => 'integer',
        'use_count' => 'integer',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->use_count >= $this->max_uses;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
