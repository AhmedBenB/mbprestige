<?php

namespace App\DataTransferObjects;

use App\Models\CustomerSearch;

class SearchCriteriaData
{
    public function __construct(
        public readonly string $make,
        public readonly ?string $model,
        public readonly float $budgetMax,
        public readonly int $yearMin,
        public readonly ?string $fuel,
        public readonly ?string $transmission,
        public readonly ?int $mileageMax,
        public readonly int $mileageTolerance,
        public readonly ?string $color,
        public readonly string $sourceZone,
    ) {}

    public static function fromModel(CustomerSearch $search): self
    {
        return new self(
            make: $search->make,
            model: $search->model,
            budgetMax: $search->budget_max,
            yearMin: $search->year_min,
            fuel: $search->fuel,
            transmission: $search->transmission,
            mileageMax: $search->mileage_max,
            mileageTolerance: $search->mileage_tolerance ?? 10000,
            color: $search->color,
            sourceZone: $search->source_zone
        );
    }

    public function toConnectorPayload(): array
    {
        return array_filter([
            'zone' => $this->sourceZone,
            'make' => $this->make,
            'model' => $this->model,
            'price_max' => $this->budgetMax,
            'year_min' => $this->yearMin,
            'fuel' => $this->fuel,
            'transmission' => $this->transmission,
            'mileage_max' => $this->mileageMax,
            'color' => $this->color,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
