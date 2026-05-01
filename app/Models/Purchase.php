<?php

namespace App\Models;

use App\Enums\PurchaseStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'user_id',
        'listing_id',
        'organization_id',
        'payment_id',
        'status',
        'reserved_at',
        'deposit_paid_at',
        'expires_at',
    ];

    protected $casts = [
        'status' => PurchaseStatusEnum::class,
        'reserved_at' => 'datetime',
        'deposit_paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActiveReservation(): bool
    {
        return in_array($this->status, [
            PurchaseStatusEnum::Reserved,
            PurchaseStatusEnum::DepositPending,
        ], true) && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
