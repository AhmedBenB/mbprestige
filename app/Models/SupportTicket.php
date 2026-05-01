<?php

namespace App\Models;

use App\Enums\SupportTicketPriorityEnum;
use App\Enums\SupportTicketStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'listing_id',
        'subject',
        'status',
        'priority',
        'handled_by',
        'handled_at',
        'resolved_at',
        'last_message_at',
    ];

    protected $casts = [
        'status' => SupportTicketStatusEnum::class,
        'priority' => SupportTicketPriorityEnum::class,
        'handled_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->latest('id');
    }
}
