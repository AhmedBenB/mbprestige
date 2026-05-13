<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerSearch extends Model
{
    protected $fillable = [
        'parent_search_id',
        'created_by',
        'user_id',
        'organization_id',
        'client_name',
        'client_first_name',
        'client_last_name',
        'client_email',
        'client_phone',
        'client_comment',
        'consent_email',
        'consent_sms',
        'make',
        'model',
        'budget_max',
        'year_min',
        'fuel',
        'transmission',
        'mileage_max',
        'mileage_tolerance',
        'color',
        'source_zone',
        'status',
        'last_run_at',
        'manage_token',
        'unsubscribe_token',
    ];

    protected $casts = [
        'budget_max' => 'float',
        'year_min' => 'integer',
        'mileage_max' => 'integer',
        'mileage_tolerance' => 'integer',
        'last_run_at' => 'datetime',
        'consent_email' => 'boolean',
        'consent_sms' => 'boolean',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_search_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function clientAccount(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SearchRun::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SearchResult::class);
    }

    public function distributedChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_search_id');
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(SearchRun::class)->latestOfMany();
    }

    public function isDistributedRoot(): bool
    {
        return $this->parent_search_id === null && $this->organization_id === null;
    }

    public function isDistributedChild(): bool
    {
        return $this->parent_search_id !== null;
    }

    public function getCriteriaSummaryAttribute(): string
    {
        $parts = array_filter([
            $this->make,
            $this->model,
            number_format($this->budget_max, 0, ',', ' ') . ' EUR',
            'annee min ' . $this->year_min,
        ]);

        if ($this->fuel) {
            $parts[] = $this->fuel;
        }

        if ($this->transmission) {
            $parts[] = $this->transmission;
        }

        if ($this->mileage_max) {
            $parts[] = 'km max ' . number_format($this->mileage_max, 0, ',', ' ');
        }

        if ($this->color) {
            $parts[] = $this->color;
        }

        return implode(' | ', $parts);
    }

    public function getClientFirstNameAttribute($value): ?string
    {
        if ($value) {
            return $value;
        }

        [$firstName] = $this->splitClientName();

        return $firstName;
    }

    public function getClientLastNameAttribute($value): ?string
    {
        if ($value) {
            return $value;
        }

        [, $lastName] = $this->splitClientName();

        return $lastName;
    }

    public function getClientFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->client_first_name,
            $this->client_last_name,
        ]);

        if ($parts !== []) {
            return trim(implode(' ', $parts));
        }

        return trim((string) $this->client_name);
    }

    private function splitClientName(): array
    {
        $fullName = trim((string) $this->client_name);

        if ($fullName === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $fullName, 2) ?: [];
        $firstName = $parts[0] ?? null;
        $lastName = $parts[1] ?? null;

        return [$firstName, $lastName];
    }
}
