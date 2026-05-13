<?php

namespace App\Models;

use App\Notifications\ClientVerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'organization_id',
    'first_name',
    'last_name',
    'date_of_birth',
    'email',
    'phone',
    'password',
    'is_admin',
    'role',
    'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_CLIENT = 'client';

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->syncRoleFlags();
            $user->syncDisplayName();
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function customerSearches(): HasMany
    {
        return $this->hasMany(CustomerSearch::class, 'user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    
    public function createdSearches(): HasMany
    {
        return $this->hasMany(CustomerSearch::class, 'created_by');
    }

    public function createdAssociationCodes(): HasMany
    {
        return $this->hasMany(ClientAssociationCode::class, 'created_by_user_id');
    }

    public function associationRequests(): HasMany
    {
        return $this->hasMany(ClientAssociationRequest::class, 'user_id');
    }

    public function reviewedAssociationRequests(): HasMany
    {
        return $this->hasMany(ClientAssociationRequest::class, 'reviewed_by_user_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isPartnerAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isClient(): bool
    {
        return ! $this->isAdmin();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new ClientVerifyEmailNotification());
    }

    private function syncRoleFlags(): void
    {
        $role = strtolower(trim((string) $this->role));

        if ($role === self::ROLE_SUPER_ADMIN) {
            $this->role = self::ROLE_SUPER_ADMIN;
            $this->is_admin = true;

            return;
        }

        if ($this->is_admin || $role === self::ROLE_ADMIN) {
            $this->role = self::ROLE_ADMIN;
            $this->is_admin = true;

            return;
        }

        $this->role = self::ROLE_CLIENT;
        $this->is_admin = false;
    }

    private function syncDisplayName(): void
    {
        $parts = array_filter([
            trim((string) $this->first_name),
            trim((string) $this->last_name),
        ]);

        if ($parts !== []) {
            $this->name = implode(' ', $parts);

            return;
        }

        if (trim((string) $this->name) === '') {
            $this->name = (string) $this->email;
        }
    }
        public function isAdmin(): bool
    {
        return (bool) ($this->is_admin ?? false)
            || in_array(strtolower((string) ($this->role ?? '')), ['admin', 'super_admin'], true);
    }
}
