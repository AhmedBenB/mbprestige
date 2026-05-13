<?php

namespace App\Services\Imports\EcarsTrade;

use App\DataTransferObjects\EcarsTradeListingData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EcarsTradeListingMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(EcarsTradeListingData $listing): array
    {
        $raw = $listing->rawPayload;
        $listingType = $this->detectListingType($raw);
        $priceVisible = $listing->price !== null;

        return [
            'external_id' => (string) ($listing->sourceRef ?: md5($listing->url)),
            'title' => $listing->title,
            'slug' => $this->buildSlug($listing),
            'listing_url' => $listing->url,
            'listing_type' => $listingType,
            'source_status' => (string) data_get($raw, 'status', 'available'),
            'currency' => (string) data_get($raw, 'currency', 'EUR'),
            'price_visible' => $priceVisible,
            'price_amount' => $listing->price,
            'auction_end_at' => $this->parseAuctionEndAt($raw),
            'make' => $listing->make,
            'model' => $listing->model,
            'year' => $listing->year,
            'mileage' => $listing->mileage,
            'fuel' => $listing->fuel,
            'transmission' => $listing->gearbox,
            'color' => $listing->color,
            'country' => (string) data_get($raw, 'country', data_get($raw, 'origin_country', '')),
            'location' => (string) data_get($raw, 'location', ''),
            'images' => $this->extractImages($raw),
            'technical_data' => $this->extractTechnicalData($raw),
            'equipment' => $this->extractEquipment($raw),
            'source_payload' => $raw,
            'source_created_at' => $this->parseDate(data_get($raw, 'created_at')),
            'source_updated_at' => $this->parseDate(data_get($raw, 'updated_at')),
            'last_seen_at' => now(),
            'status' => $priceVisible ? 'ready_for_review' : 'draft',
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<int, string>
     */
    private function extractImages(array $raw): array
    {
        $images = $this->firstArrayByPaths($raw, [
            'images',
            'media.images',
            'raw.images',
            'raw.photos',
            'raw.gallery',
            'raw.media.images',
            'raw.api_data.images',
            'raw.api_data.photos',
            'raw.api_data.gallery',
            'raw.api_data.media.images',
        ]);

        if (!is_array($images)) {
            $images = [];
        }

        $normalized = array_values(array_filter(array_map(static function ($value): ?string {
            if (is_string($value)) {
                return trim($value) !== '' ? trim($value) : null;
            }

            if (is_array($value)) {
                $candidate = $value['url'] ?? $value['src'] ?? null;
                if (is_string($candidate) && trim($candidate) !== '') {
                    return trim($candidate);
                }
            }

            return null;
        }, $images)));

        if ($normalized !== []) {
            return $normalized;
        }

        return $this->extractImageUrlsFromHtml((string) data_get($raw, 'raw.card_html_excerpt', ''));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function extractTechnicalData(array $raw): array
    {
        $fields = [
            'vin',
            'engine_power',
            'doors',
            'seats',
            'emission_class',
            'first_registration',
        ];

        $technical = [];
        foreach ($fields as $field) {
            $value = data_get($raw, $field);
            if ($value !== null && $value !== '') {
                $technical[$field] = $value;
            }
        }

        return $technical;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<int, string>
     */
    private function extractEquipment(array $raw): array
    {
        $equipment = data_get($raw, 'equipment', data_get($raw, 'options', []));
        if (!is_array($equipment)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($item): ?string {
            if (!is_string($item)) {
                return null;
            }

            $value = trim($item);

            return $value !== '' ? $value : null;
        }, $equipment)));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function detectListingType(array $raw): string
    {
        $auctionType = strtolower((string) data_get($raw, 'auction_type', ''));
        $saleType = strtolower((string) data_get($raw, 'sale_type', ''));

        if (str_contains($saleType, 'fixed') || str_contains($auctionType, 'fixed')) {
            return 'fixed_price';
        }

        if (str_contains($auctionType, 'blind')) {
            return 'auction_blind';
        }

        if (str_contains($auctionType, 'open')) {
            return 'auction_open';
        }

        return 'auction_open';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function parseAuctionEndAt(array $raw): ?string
    {
        foreach (['auction_end_at', 'ends_at', 'end_date'] as $field) {
            $value = data_get($raw, $field);
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                return Carbon::parse($value)->toDateTimeString();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildSlug(EcarsTradeListingData $listing): string
    {
        $label = trim(implode(' ', array_filter([
            $listing->make,
            $listing->model,
            $listing->year,
            $listing->sourceRef,
        ])));

        if ($label === '') {
            $label = 'ecarstrade-listing-' . md5($listing->url);
        }

        return Str::slug($label);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<int, string>  $paths
     */
    private function firstArrayByPaths(array $raw, array $paths): ?array
    {
        foreach ($paths as $path) {
            $value = data_get($raw, $path);
            if (is_array($value) && $value !== []) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractImageUrlsFromHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        preg_match_all('/https?:\/\/[^"\']+\.(?:jpg|jpeg|png|webp|gif)(?:\?[^"\']*)?/i', $html, $matches);
        if (!isset($matches[0]) || !is_array($matches[0])) {
            return [];
        }

        return array_values(array_unique(array_map(static fn (string $url): string => trim($url), $matches[0])));
    }
}
