<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name', 'legal_name', 'vat_number', 'country', 'city',
        'address', 'zip_code', 'status', 'deposit_balance', 'credit_limit', 'user_tier',
    ];

    protected $casts = [
        'deposit_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function isGolden(): bool { return $this->user_tier === 'golden'; }
    public function isSilver(): bool { return $this->user_tier === 'silver'; }
    public function isTrial(): bool  { return $this->user_tier === 'trial'; }

    public function maxActiveBids(): int
    {
        return match($this->user_tier) {
            'golden' => 999,
            'silver' => 50,
            default  => 5,
        };
    }
}
