<?php

namespace App\DataTransferObjects;

class EcarsTradeListingData
{
    public function __construct(
        public readonly ?string $sourceRef,
        public readonly string $url,
        public readonly ?string $title,
        public readonly ?string $make,
        public readonly ?string $model,
        public readonly ?float $price,
        public readonly ?int $year,
        public readonly ?string $fuel,
        public readonly ?string $gearbox,
        public readonly ?int $mileage,
        public readonly ?string $color,
        public readonly array $rawPayload = [],
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            sourceRef: $payload['source_ref'] ?? $payload['id'] ?? null,
            url: $payload['url'],
            title: $payload['title'] ?? null,
            make: $payload['make'] ?? null,
            model: $payload['model'] ?? null,
            price: isset($payload['price']) ? (float) $payload['price'] : null,
            year: isset($payload['year']) ? (int) $payload['year'] : null,
            fuel: $payload['fuel'] ?? null,
            gearbox: $payload['gearbox'] ?? $payload['transmission'] ?? null,
            mileage: isset($payload['mileage']) ? (int) $payload['mileage'] : null,
            color: $payload['color'] ?? null,
            rawPayload: $payload
        );
    }
}
