<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'filters_json',
        'notify_email',
        'notify_push',
        'last_notified_at',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'notify_email' => 'boolean',
        'notify_push' => 'boolean',
        'last_notified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
