<?php

namespace App\Http\Controllers;

use App\Models\ExternalListing;
use App\Models\ListingSimilarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicExternalListingController extends Controller
{
    public function show(string $identifier)
    {
        $listing = $this->resolveListing($identifier);
        $this->trackView($listing, request());

        return view('external-listing-show', [
            'listing' => $listing,
            'payload' => $this->buildPayload($listing),
        ]);
    }

    public function showApi(string $identifier): JsonResponse
    {
        $listing = $this->resolveListing($identifier);

        return response()->json([
            'data' => $this->buildPayload($listing),
        ]);
    }

    private function resolveListing(string $identifier): ExternalListing
    {
        $query = ExternalListing::query()
            ->with([
                'source',
                'documents',
                'bids.user:id,name,email',
                'priceEstimates',
                'similarities' => function ($builder): void {
                    $builder
                        ->orderByDesc('score')
                        ->limit(6)
                        ->with(['similarListing.latestPriceEstimate']);
                },
            ])
            ->whereIn('status', [
                ExternalListing::STATUS_PUBLISHED,
                ExternalListing::STATUS_EXPIRED,
                ExternalListing::STATUS_READY_FOR_REVIEW,
            ]);

        if (ctype_digit($identifier)) {
            $listing = (clone $query)->whereKey((int) $identifier)->first();
            if ($listing) {
                return $listing;
            }
        }

        return $query->where('slug', $identifier)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(ExternalListing $listing): array
    {
        $displayMargin = (float) config('ecarstrade.import.display_margin', config('ecarstrade.import.margin_min', 2000));
        $estimate = $listing->priceEstimates->sortByDesc('id')->first();
        $images = is_array($listing->images) ? array_values($listing->images) : [];
        $technical = is_array($listing->technical_data) ? $listing->technical_data : [];
        $equipment = is_array($listing->equipment) ? $listing->equipment : [];
        $sourcePayload = is_array($listing->source_payload) ? $listing->source_payload : [];
        $model = $this->resolveModelLabel($listing);
        $color = $this->resolveColorLabel($listing, $sourcePayload);
        $fuel = $this->normalizeFuelLabel((string) ($listing->fuel ?? ''));
        $transmission = $this->normalizeTransmissionLabel((string) ($listing->transmission ?? ''));
        $isAuction = str_starts_with((string) $listing->listing_type, 'auction_');
        $isExpired = ((string) $listing->status === ExternalListing::STATUS_EXPIRED)
            || ($listing->auction_end_at && $listing->auction_end_at->isPast());
        $topBid = $listing->bids->sortByDesc('amount')->first();
        $auctionAvailable = $isAuction && !$isExpired;

        $similar = $listing->similarities
            ->map(function (ListingSimilarity $similarity) use ($displayMargin): ?array {
                $item = $similarity->similarListing;
                if (!$item) {
                    return null;
                }

                $estimate = $item->latestPriceEstimate;
                return [
                    'id' => $item->id,
                    'slug' => $item->slug,
                    'title' => $item->title ?: trim(($item->make ?: '') . ' ' . ($item->model ?: '')),
                    'year' => $item->year,
                    'mileage' => $item->mileage,
                    'price_visible' => (bool) $item->price_visible,
                    'source_price_amount' => $item->price_amount,
                    'price_amount' => $item->price_amount !== null ? ((float) $item->price_amount + $displayMargin) : null,
                    'estimate_min' => $estimate?->estimated_price_min,
                    'estimate_max' => $estimate?->estimated_price_max,
                    'score' => $similarity->score,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $listing->id,
            'external_id' => $listing->external_id,
            'title' => $listing->title ?: trim(($listing->make ?: '') . ' ' . ($model ?: '')),
            'slug' => $listing->slug,
            'listing_url' => $listing->listing_url,
            'status' => $listing->status,
            'is_expired' => $isExpired,
            'is_auction' => $isAuction,
            'auction_available' => $auctionAvailable,
            'listing_type' => $listing->listing_type,
            'source_status' => $listing->source_status,
            'currency' => $listing->currency,
            'price_visible' => (bool) $listing->price_visible,
            'source_price_amount' => $listing->price_amount,
            'price_margin_amount' => $displayMargin,
            'price_amount' => $listing->price_amount !== null ? ((float) $listing->price_amount + $displayMargin) : null,
            'price_display_type' => $listing->price_amount !== null ? 'fixed_or_current' : ($estimate ? 'estimate' : 'hidden'),
            'auction_end_at' => optional($listing->auction_end_at)->toIso8601String(),
            'make' => $listing->make,
            'model' => $model,
            'year' => $listing->year,
            'mileage' => $listing->mileage,
            'fuel' => $fuel,
            'transmission' => $transmission,
            'color' => $color,
            'views_count' => (int) $listing->views_count,
            'bids_count' => $listing->bids->count(),
            'top_bid_amount' => $topBid?->amount,
            'top_bidder' => $topBid?->user ? [
                'name' => $topBid->user->name,
                'email' => $topBid->user->email,
            ] : null,
            'images' => $images,
            'technical_data' => $technical,
            'equipment' => array_values($equipment),
            'documents' => $listing->documents
                ->map(fn ($doc) => [
                    'id' => $doc->id,
                    'type' => $doc->document_type,
                    'title' => $doc->title,
                    'file_url' => $doc->file_url,
                    'file_name' => $doc->file_name,
                    'mime_type' => $doc->mime_type,
                ])->values()->all(),
            'price_estimation' => $estimate ? [
                'min' => $estimate->estimated_price_min,
                'max' => $estimate->estimated_price_max,
                'confidence' => $estimate->estimated_price_confidence,
                'confidence_label' => $estimate->confidence_label,
                'reason' => $estimate->estimated_price_reason,
                'sample_size' => $estimate->sample_size,
            ] : null,
            'history_report' => [
                'summary' => $sourcePayload['history_summary'] ?? $sourcePayload['report_summary'] ?? null,
                'accident' => $sourcePayload['accident_report'] ?? null,
                'maintenance' => $sourcePayload['maintenance_report'] ?? null,
                'ownership' => $sourcePayload['ownership_history'] ?? null,
            ],
            'source' => [
                'name' => $listing->source?->name,
                'code' => $listing->source?->code,
                'base_url' => $listing->source?->base_url,
                'external_id' => $listing->external_id,
                'last_seen_at' => optional($listing->last_seen_at)->toIso8601String(),
                'source_updated_at' => optional($listing->source_updated_at)->toIso8601String(),
            ],
            'purchase_conditions' => $this->purchaseConditions($listing),
            'similar_listings' => $similar,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function purchaseConditions(ExternalListing $listing): array
    {
        $conditions = [
            'Validation manuelle MBPRESTIGE avant toute publication finale.',
            'Acompte demande avant reservation definitive.',
            'Solde regle apres validation administrative et documents.',
        ];

        if (str_starts_with($listing->listing_type, 'auction')) {
            $conditions[] = 'Annonce en enchere: le montant final peut evoluer jusqu a la cloture.';
            $conditions[] = 'Le chronometre et la disponibilite peuvent changer selon la source.';
        }

        if (!$listing->price_visible) {
            $conditions[] = 'Prix source non visible: estimation affichee a titre indicatif uniquement.';
        }

        return $conditions;
    }

    private function resolveModelLabel(ExternalListing $listing): string
    {
        $model = $this->cleanNullableText((string) ($listing->model ?? ''));
        if ($model !== '') {
            return $model;
        }

        $title = trim((string) ($listing->title ?? ''));
        if ($title === '') {
            return '-';
        }

        $make = trim((string) ($listing->make ?? ''));
        if ($make !== '' && str_starts_with(strtolower($title), strtolower($make))) {
            $title = trim(substr($title, strlen($make)));
        }

        $parts = array_values(array_filter(array_map('trim', preg_split('/[\/|]+/', $title) ?: [])));
        $base = $parts[0] ?? $title;
        $base = preg_replace('/\b(19|20)\d{2}\b/u', '', $base) ?? $base;
        $base = trim((string) preg_replace('/\s+/', ' ', $base));

        preg_match('/\b(\d{2,3}[a-z]{0,2})\b/i', $title, $match);
        $engineCode = strtoupper((string) ($match[1] ?? ''));
        if ($engineCode !== '' && stripos($base, $engineCode) === false) {
            $base .= ' ' . $engineCode;
        }

        $base = trim((string) $base);
        return $base !== '' ? $base : '-';
    }

    private function resolveColorLabel(ExternalListing $listing, array $payload): string
    {
        $color = $this->cleanNullableText((string) ($listing->color ?? ''));
        if ($color !== '') {
            return $color;
        }

        $candidates = [
            data_get($payload, 'color'),
            data_get($payload, 'body_color'),
            data_get($payload, 'exterior_color'),
            data_get($payload, 'technical_data.color'),
            data_get($payload, 'raw.color'),
            data_get($payload, 'raw.body_color'),
            data_get($payload, 'raw.exterior_color'),
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $clean = $this->cleanNullableText($candidate);
            if ($clean !== '') {
                return $clean;
            }
        }

        return '-';
    }

    private function cleanNullableText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $lower = mb_strtolower($value);
        if (in_array($lower, ['-', '--', 'n/a', 'na', 'none', 'null', 'unknown', 'other'], true)) {
            return '';
        }

        return $value;
    }

    private function normalizeFuelLabel(string $value): string
    {
        $raw = $this->cleanNullableText($value);
        if ($raw === '') {
            return '-';
        }

        $lower = mb_strtolower($raw);
        return match (true) {
            str_contains($lower, 'diesel') => 'diesel',
            str_contains($lower, 'essence'), str_contains($lower, 'petrol') => 'essence',
            str_contains($lower, 'electri'), str_contains($lower, 'ã©lectri'), str_contains($lower, 'ev') => 'electrique',
            str_contains($lower, 'hybrid') => 'hybride',
            str_contains($lower, 'gpl'), str_contains($lower, 'lpg') => 'gpl',
            str_contains($lower, 'gaz'), str_contains($lower, 'cng') => 'gaz',
            default => $lower,
        };
    }

    private function normalizeTransmissionLabel(string $value): string
    {
        $raw = $this->cleanNullableText($value);
        if ($raw === '') {
            return '-';
        }

        $lower = mb_strtolower($raw);
        return match (true) {
            in_array($lower, ['automatic', 'automatique', 'auto', 'aut8'], true) => 'automatic',
            in_array($lower, ['manual', 'manuel', 'manuelle'], true) => 'manual',
            str_contains($lower, 'semi') || str_contains($lower, 'direct no gearbox') => 'semi-automatic',
            default => $lower,
        };
    }

    private function trackView(ExternalListing $listing, Request $request): void
    {
        $viewer = (string) ($request->user()?->id ?? $request->ip() ?? 'guest');
        $key = "external_listing:viewed:{$listing->id}:{$viewer}:" . now()->format('Ymd');

        if (!Cache::add($key, true, now()->addHours(12))) {
            return;
        }

        $listing->increment('views_count');
    }
}

