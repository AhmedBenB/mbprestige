<?php

namespace App\Http\Controllers;

use App\Models\ExternalListing;
use App\Models\ListingSimilarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicExternalListingController extends Controller
{
    public function show(string $identifier)
    {
        $listing = $this->resolveListing($identifier);

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
        $estimate = $listing->priceEstimates->sortByDesc('id')->first();
        $images = is_array($listing->images) ? array_values($listing->images) : [];
        $technical = is_array($listing->technical_data) ? $listing->technical_data : [];
        $equipment = is_array($listing->equipment) ? $listing->equipment : [];
        $sourcePayload = is_array($listing->source_payload) ? $listing->source_payload : [];

        $similar = $listing->similarities
            ->map(function (ListingSimilarity $similarity): ?array {
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
                    'price_amount' => $item->price_amount,
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
            'title' => $listing->title ?: trim(($listing->make ?: '') . ' ' . ($listing->model ?: '')),
            'slug' => $listing->slug,
            'listing_url' => $listing->listing_url,
            'status' => $listing->status,
            'listing_type' => $listing->listing_type,
            'source_status' => $listing->source_status,
            'currency' => $listing->currency,
            'price_visible' => (bool) $listing->price_visible,
            'price_amount' => $listing->price_amount,
            'auction_end_at' => optional($listing->auction_end_at)->toIso8601String(),
            'make' => $listing->make,
            'model' => $listing->model,
            'year' => $listing->year,
            'mileage' => $listing->mileage,
            'fuel' => $listing->fuel,
            'transmission' => $listing->transmission,
            'color' => $listing->color,
            'country' => $listing->country,
            'location' => $listing->location,
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
            'Acompte demandé avant réservation définitive.',
            'Solde réglé après validation administrative et documents.',
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
}
