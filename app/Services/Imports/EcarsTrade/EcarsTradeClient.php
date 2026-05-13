<?php

namespace App\Services\Imports\EcarsTrade;

use App\DataTransferObjects\EcarsTradeListingData;
use App\DataTransferObjects\SearchCriteriaData;
use App\Models\Source;
use App\Services\EcarsTrade\Contracts\EcarsTradeConnectorInterface;
use Illuminate\Support\Facades\Log;

class EcarsTradeClient
{
    public function __construct(
        private readonly EcarsTradeConnectorInterface $connector,
    ) {
    }

    /**
     * @return array<int, EcarsTradeListingData>
     */
    public function fetchLatestListings(Source $source, int $limit = 20): array
    {
        $this->connector->authenticate();

        $meta = is_array($source->meta) ? $source->meta : [];
        $makes = $this->resolveMakes($meta);
        $limit = max(1, $limit);

        $seen = [];
        $results = [];

        foreach ($makes as $make) {
            try {
                $criteria = new SearchCriteriaData(
                    make: $make,
                    model: null,
                    budgetMax: (float) ($meta['budget_max'] ?? 150000),
                    yearMin: (int) ($meta['year_min'] ?? 2005),
                    fuel: null,
                    transmission: null,
                    mileageMax: null,
                    mileageTolerance: 60000,
                    color: null,
                    sourceZone: (string) ($meta['zone'] ?? 'all_cars'),
                );

                $batch = $this->connector->search($criteria);
                foreach ($batch as $listing) {
                    $externalId = trim((string) ($listing->sourceRef ?: $listing->url));
                    if ($externalId === '' || isset($seen[$externalId])) {
                        continue;
                    }

                    $seen[$externalId] = true;
                    $results[] = $this->enrichWithDetails($listing, $source, $meta);

                    if (count($results) >= $limit) {
                        return $results;
                    }
                }

                // Slow and respectful import cadence.
                usleep(350000);
            } catch (\Throwable $exception) {
                Log::warning('eCarsTrade import batch failed for one make', [
                    'source_id' => $source->id,
                    'make' => $make,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $results;
    }

    private function enrichWithDetails(EcarsTradeListingData $listing, Source $source, array $meta): EcarsTradeListingData
    {
        $fetchDetails = (bool) ($meta['fetch_details'] ?? config('ecarstrade.import.fetch_details', true));
        if (!$fetchDetails) {
            return $listing;
        }

        try {
            $details = $this->connector->fetchListingDetails($listing);
            $rawPayload = $listing->rawPayload;
            $rawPayload['details'] = $details;

            $enriched = EcarsTradeListingData::fromArray([
                'source_ref' => $listing->sourceRef,
                'url' => $listing->url,
                'title' => $listing->title,
                'make' => $listing->make,
                'model' => $listing->model,
                'price' => $listing->price,
                'year' => $listing->year,
                'fuel' => $listing->fuel,
                'gearbox' => $listing->gearbox,
                'mileage' => $listing->mileage,
                'color' => $listing->color,
                'raw' => $rawPayload,
                'images' => is_array($details['images'] ?? null) ? $details['images'] : [],
                'documents' => is_array($details['documents'] ?? null) ? $details['documents'] : [],
            ]);

            $delayMs = max(0, (int) ($meta['detail_delay_ms'] ?? config('ecarstrade.import.detail_delay_ms', 150)));
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            return $enriched;
        } catch (\Throwable $exception) {
            Log::warning('eCarsTrade listing detail enrichment failed', [
                'source_id' => $source->id,
                'source_ref' => $listing->sourceRef,
                'url' => $listing->url,
                'message' => $exception->getMessage(),
            ]);

            return $listing;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<int, string>
     */
    private function resolveMakes(array $meta): array
    {
        $fromMeta = $meta['import_makes'] ?? null;
        if (is_array($fromMeta)) {
            $normalized = array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : '', $fromMeta)));
            if ($normalized !== []) {
                return $normalized;
            }
        }

        return ['BMW', 'Mercedes', 'Peugeot', 'Renault', 'Volkswagen', 'Audi', 'Toyota'];
    }
}
