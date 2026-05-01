<?php

namespace App\Models;

use App\Enums\PaymentStatusEnum;
use App\Enums\PaymentTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'listing_id',
        'organization_id',
        'purchase_id',
        'type',
        'provider',
        'provider_session_id',
        'provider_payment_intent_id',
        'amount',
        'currency',
        'status',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'type' => PaymentTypeEnum::class,
        'status' => PaymentStatusEnum::class,
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
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

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
