<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSourceAccount extends Model
{
    public const SOURCE_ECARSTRADE = 'ecarstrade';
    public const STATUS_NEVER_TESTED = 'never_tested';
    public const STATUS_OK = 'ok';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'source',
        'login_email',
        'login_username',
        'encrypted_password',
        'base_url',
        'is_active',
        'last_auth_status',
        'last_auth_error',
        'last_auth_checked_at',
    ];

    protected $casts = [
        'encrypted_password' => 'encrypted',
        'is_active' => 'boolean',
        'last_auth_checked_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function hasCredentials(): bool
    {
        return $this->loginIdentifier() !== ''
            && trim((string) $this->encrypted_password) !== '';
    }

    public function loginIdentifier(): string
    {
        return trim((string) ($this->login_username ?: $this->login_email));
    }
}
