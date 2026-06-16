<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'location',
        'description',
        'slug',
        'partner_code',
        'admin_code',
        'is_active',
        'user_tier',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function adminUsers(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_ADMIN);
    }

    public function clientUsers(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_CLIENT);
    }

    public function searches(): HasMany
    {
        return $this->hasMany(CustomerSearch::class);
    }

    public function associationCodes(): HasMany
    {
        return $this->hasMany(ClientAssociationCode::class);
    }

    public function associationRequests(): HasMany
    {
        return $this->hasMany(ClientAssociationRequest::class);
    }

    public function ecarsTradeAccount(): HasOne
    {
        return $this->hasOne(OrganizationSourceAccount::class)
            ->where('source', OrganizationSourceAccount::SOURCE_ECARSTRADE);
    }

    public function maxActiveBids(): int
    {   
        return match ((string) $this->user_tier) {
            'golden' => 50,
            'silver' => 20,
            default => 5,
        };
    }

}
