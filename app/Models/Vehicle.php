<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'vin', 'make', 'model', 'version', 'body_type', 'fuel_type',
        'gearbox', 'transmission', 'engine_size_cc', 'power_hp', 'power_kw',
        'co2', 'doors', 'seats', 'color', 'color_code', 'origin_country',
        'first_registration_date', 'mileage', 'emission_class', 'service_history',
    ];

    protected $casts = [
        'first_registration_date' => 'date',
    ];

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->make} {$this->model} {$this->version}");
    }

    public function getRegistrationYearAttribute(): ?int
    {
        return $this->first_registration_date?->year;
    }

    public function scopeByMake($query, string $make)
    {
        return $query->where('make', $make);
    }

    public function scopeByModel($query, string $make, string $model)
    {
        return $query->where('make', $make)->where('model', $model);
    }
}
