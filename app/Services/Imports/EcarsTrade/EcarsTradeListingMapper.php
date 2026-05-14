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
        $listingType = $this->detectListingType($raw, $listing->price);
        $priceVisible = $listing->price !== null;
        $auctionEndAt = $this->parseAuctionEndAt($raw);

        $status = 'draft';
        if ($auctionEndAt !== null && Carbon::parse($auctionEndAt)->isPast()) {
            $status = 'expired';
        } elseif ($priceVisible || $auctionEndAt !== null) {
            $status = 'ready_for_review';
        }

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
            'auction_end_at' => $auctionEndAt,
            'make' => $listing->make,
            'model' => $this->resolveModel($listing),
            'year' => $listing->year,
            'mileage' => $listing->mileage,
            'fuel' => $this->normalizeFuel((string) $listing->fuel),
            'transmission' => $this->normalizeTransmission((string) $listing->gearbox),
            'color' => $this->normalizeColor((string) $listing->color),
            'country' => (string) data_get($raw, 'country', data_get($raw, 'origin_country', '')),
            'location' => (string) data_get($raw, 'location', ''),
            'images' => $this->extractImages($raw),
            'technical_data' => $this->extractTechnicalData($raw),
            'equipment' => $this->extractEquipment($raw),
            'source_payload' => $raw,
            'source_created_at' => $this->parseDate(data_get($raw, 'created_at')),
            'source_updated_at' => $this->parseDate(data_get($raw, 'updated_at')),
            'last_seen_at' => now(),
            'status' => $status,
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
            'details.images',
            'raw.images',
            'raw.photos',
            'raw.gallery',
            'raw.media.images',
            'raw.details.images',
            'raw.api_data.images',
            'raw.api_data.photos',
            'raw.api_data.gallery',
            'raw.api_data.media.images',
        ]);

        if (!is_array($images)) {
            $images = [];
        }

        $normalized = array_values(array_filter(array_map(function ($value): ?string {
            if (is_string($value)) {
                $url = trim($value);
                return $url !== '' ? $this->preferHighDefinitionImageUrl($url) : null;
            }

            if (is_array($value)) {
                $candidate = $value['url'] ?? $value['src'] ?? $value['image'] ?? $value['photo'] ?? null;
                if (is_string($candidate) && trim($candidate) !== '') {
                    return $this->preferHighDefinitionImageUrl(trim($candidate));
                }
            }

            return null;
        }, $images)));

        if ($normalized !== []) {
            return $this->sortAndDedupeImages($normalized);
        }

        $fromPayload = $this->sortAndDedupeImages(array_map(
            fn (string $url): string => $this->preferHighDefinitionImageUrl($url),
            $this->extractImageUrlsFromPayload($raw)
        ));
        if ($fromPayload !== []) {
            return $fromPayload;
        }

        return $this->sortAndDedupeImages(array_map(
            fn (string $url): string => $this->preferHighDefinitionImageUrl($url),
            $this->extractImageUrlsFromHtml((string) data_get($raw, 'raw.card_html_excerpt', ''))
        ));
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
    private function detectListingType(array $raw, ?float $price): string
    {
        $auctionType = mb_strtolower((string) data_get($raw, 'auction_type', ''));
        $saleType = mb_strtolower((string) data_get($raw, 'sale_type', ''));
        $status = mb_strtolower((string) data_get($raw, 'status', ''));
        $hasAuctionEnd = $this->parseAuctionEndAt($raw) !== null;

        if (str_contains($saleType, 'fixed') || str_contains($auctionType, 'fixed')) {
            return 'fixed_price';
        }

        if (str_contains($saleType, 'stock')) {
            return 'partner_stock';
        }

        if (str_contains($auctionType, 'blind')) {
            return 'auction_blind';
        }

        if (str_contains($auctionType, 'open')
            || str_contains($saleType, 'auction')
            || str_contains($status, 'auction')
            || $hasAuctionEnd) {
            return 'auction_open';
        }

        if ($price !== null) {
            return 'fixed_price';
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

    private function resolveModel(EcarsTradeListingData $listing): ?string
    {
        $model = trim((string) ($listing->model ?? ''));
        if ($model !== '') {
            return $model;
        }

        $title = trim((string) ($listing->title ?? ''));
        if ($title === '') {
            return null;
        }

        $make = trim((string) ($listing->make ?? ''));
        if ($make !== '' && Str::startsWith(Str::lower($title), Str::lower($make . ' '))) {
            $title = trim((string) Str::of($title)->after($make));
        }

        $parts = array_values(array_filter(array_map('trim', preg_split('/[\/|]+/', $title) ?: [])));
        $base = $parts[0] ?? $title;
        $base = preg_replace('/\b(19|20)\d{2}\b/u', '', $base) ?? $base;
        $base = trim((string) preg_replace('/\s+/', ' ', $base));

        preg_match('/\b(\d{2,3}[a-z]{0,2})\b/i', $title, $codeMatch);
        $engineCode = strtoupper((string) ($codeMatch[1] ?? ''));

        if ($base !== '' && $engineCode !== '' && !Str::contains(Str::lower($base), Str::lower($engineCode))) {
            $base .= ' ' . $engineCode;
        }

        $base = trim($base, "- \t\n\r\0\x0B");
        return $base !== '' ? $base : null;
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

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function extractImageUrlsFromPayload(array $payload): array
    {
        $urls = [];
        array_walk_recursive($payload, static function ($value) use (&$urls): void {
            if (!is_string($value)) {
                return;
            }

            $candidate = trim($value);
            if ($candidate === '') {
                return;
            }

            if (preg_match('/^https?:\/\/[^\s"\']+$/i', $candidate) !== 1) {
                return;
            }

            if (preg_match('/\.(jpg|jpeg|png|webp|gif|avif)(\?.*)?$/i', $candidate) === 1
                || preg_match('/\/(image|images|img|photo|photos|vehicle|car)\b/i', $candidate) === 1) {
                $urls[] = $candidate;
            }
        });

        return array_values(array_unique($urls));
    }

    private function preferHighDefinitionImageUrl(string $url): string
    {
        $clean = $url;

        $clean = preg_replace('#/(thumb|thumbs|thumbnail|thumbnails|small|preview)/#i', '/', $clean) ?? $clean;
        $clean = preg_replace('/([_-])(thumb|thumbnail|small|preview)(?=\.)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/([_-])\d{2,4}x\d{2,4}(?=\.)/i', '', $clean) ?? $clean;
        $clean = preg_replace('#/(?:\d{2,4}x\d{2,4})/#i', '/', $clean) ?? $clean;

        $parts = parse_url($clean);
        if (!is_array($parts) || empty($parts['path'])) {
            return $clean;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
            foreach (['w', 'width', 'h', 'height', 'q', 'quality', 'fit', 'resize', 'dpr'] as $key) {
                unset($query[$key]);
            }
        }

        if (empty($parts['host'])) {
            return $clean;
        }

        $rebuilt = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }
        $rebuilt .= $parts['path'];
        if (!empty($query)) {
            $rebuilt .= '?' . http_build_query($query);
        }

        return $rebuilt;
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function sortAndDedupeImages(array $urls): array
    {
        $urls = array_values(array_unique(array_filter(array_map('trim', $urls))));

        usort($urls, function (string $left, string $right): int {
            return $this->imageQualityScore($right) <=> $this->imageQualityScore($left);
        });

        return $urls;
    }

    private function imageQualityScore(string $url): int
    {
        $score = 0;
        $lower = Str::lower($url);

        foreach (['thumb', 'thumbnail', 'small', 'preview', 'icon'] as $badToken) {
            if (str_contains($lower, $badToken)) {
                $score -= 25;
            }
        }

        if (preg_match('/(\d{3,4})x(\d{3,4})/i', $lower, $size) === 1) {
            $score += ((int) $size[1]) + ((int) $size[2]);
            if ((int) $size[1] <= 500 || (int) $size[2] <= 500) {
                $score -= 15;
            }
        }

        foreach (['full', 'large', 'original', 'max'] as $goodToken) {
            if (str_contains($lower, $goodToken)) {
                $score += 20;
            }
        }

        return $score;
    }

    private function normalizeFuel(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }
        $lower = mb_strtolower($raw);
        return match (true) {
            str_contains($lower, 'diesel') => 'diesel',
            str_contains($lower, 'essence'), str_contains($lower, 'petrol') => 'essence',
            str_contains($lower, 'hybrid') => 'hybride',
            str_contains($lower, 'electri'), str_contains($lower, 'ã©lectri'), str_contains($lower, 'ev') => 'electrique',
            str_contains($lower, 'gpl'), str_contains($lower, 'lpg') => 'gpl',
            str_contains($lower, 'gaz'), str_contains($lower, 'cng') => 'gaz',
            default => null,
        };
    }

    private function normalizeTransmission(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }
        $lower = mb_strtolower($raw);
        return match (true) {
            in_array($lower, ['automatic', 'automatique', 'auto', 'aut8'], true) => 'automatic',
            in_array($lower, ['manual', 'manuel', 'manuelle'], true) => 'manual',
            str_contains($lower, 'semi'), str_contains($lower, 'direct no gearbox') => 'semi-automatic',
            default => null,
        };
    }

    private function normalizeColor(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '' || in_array(mb_strtolower($raw), ['-', '--', 'n/a', 'na', 'none', 'unknown'], true)) {
            return null;
        }
        return $raw;
    }
}
