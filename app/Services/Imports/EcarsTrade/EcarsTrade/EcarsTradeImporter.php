<?php

namespace App\Services\Imports\EcarsTrade;

use App\DataTransferObjects\EcarsTradeListingData;
use App\Models\ExternalListing;
use App\Models\ListingDocument;
use App\Models\Source;
use App\Models\SourceImport;
use App\Models\SourceImportItem;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EcarsTradeImporter
{
    public function __construct(
        private readonly EcarsTradeClient $client,
        private readonly EcarsTradeListingMapper $mapper,
        private readonly EcarsTradePriceEstimator $priceEstimator,
        private readonly EcarsTradeSimilarityService $similarityService,
    ) {
    }

    public function ensureSource(): Source
    {
        return Source::query()->updateOrCreate(
            ['code' => Source::CODE_ECARSTRADE],
            [
                'name' => 'eCarsTrade',
                'type' => 'marketplace',
                'base_url' => (string) config('ecarstrade.base_url'),
                'is_active' => (bool) config('ecarstrade.import.enabled', true),
                'meta' => [
                    'publish_media' => (bool) config('ecarstrade.import.publish_media', true),
                    'publish_documents' => (bool) config('ecarstrade.import.publish_documents', true),
                    'import_makes' => config('ecarstrade.import.makes'),
                    'budget_max' => (float) config('ecarstrade.import.budget_max', 150000),
                    'year_min' => (int) config('ecarstrade.import.year_min', 2005),
                    'margin_min' => (float) config('ecarstrade.import.margin_min', 2000),
                    'margin_max' => (float) config('ecarstrade.import.margin_max', 3000),
                    'sync_every_minutes' => (int) config('ecarstrade.import.sync_every_minutes', 30),
                    'auto_publish' => (bool) config('ecarstrade.import.auto_publish', false),
                    'rights_media' => true,
                    'rights_documents' => true,
                ],
            ]
        );
    }

    /**
     * @return SourceImport
     */
    public function run(?User $triggeredBy = null, ?int $limit = null, ?bool $autoPublish = null): SourceImport
    {
        $source = $this->ensureSource();
        $syncLimit = max(1, $limit ?? (int) config('ecarstrade.import.sync_limit', 20));
        $autoPublishEnabled = $autoPublish ?? (bool) config('ecarstrade.import.auto_publish', false);

        /** @var SourceImport $import */
        $import = SourceImport::query()->create([
            'source_id' => $source->id,
            'triggered_by_user_id' => $triggeredBy?->id,
            'status' => SourceImport::STATUS_RUNNING,
            'sync_limit' => $syncLimit,
            'started_at' => now(),
            'notes' => 'Import eCarsTrade demarre.',
        ]);

        $createdCount = 0;
        $updatedCount = 0;
        $errorCount = 0;
        $autoPublishedCount = 0;

        try {
            $listings = $this->client->fetchLatestListings($source, $syncLimit);

            foreach ($listings as $listing) {
                try {
                    $result = $this->persistListing($source, $import, $listing);
                    if ($result['status'] === 'created') {
                        $createdCount++;
                    } elseif ($result['status'] === 'updated') {
                        $updatedCount++;
                    }
                    if ($autoPublishEnabled && $this->publishIfEligible($result['listing'])) {
                        $autoPublishedCount++;
                    }
                } catch (\Throwable $exception) {
                    $errorCount++;
                    $this->registerItemError($import, $listing, $exception->getMessage());
                }
            }

            $import->update([
                'status' => SourceImport::STATUS_COMPLETED,
                'fetched_count' => count($listings),
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'error_count' => $errorCount,
                'finished_at' => now(),
                'notes' => 'Import termine. Auto-publiees: ' . $autoPublishedCount . '.',
            ]);
        } catch (\Throwable $exception) {
            $import->update([
                'status' => SourceImport::STATUS_FAILED,
                'error_count' => $errorCount + 1,
                'finished_at' => now(),
                'notes' => $exception->getMessage(),
            ]);

            Log::error('eCarsTrade import failed', [
                'source_import_id' => $import->id,
                'source_id' => $source->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return $import->fresh();
    }

    /**
     * @return array{status: 'created'|'updated', listing: ExternalListing}
     */
    private function persistListing(Source $source, SourceImport $import, EcarsTradeListingData $listing): array
    {
        $normalized = $this->mapper->map($listing);
        $externalId = (string) $normalized['external_id'];

        return DB::transaction(function () use ($source, $import, $listing, $normalized, $externalId): array {
            $existing = ExternalListing::query()
                ->where('source_id', $source->id)
                ->where('external_id', $externalId)
                ->first();

            $status = $existing ? 'updated' : 'created';
            $attributes = array_merge(
                Arr::except($normalized, ['external_id']),
                ['source_id' => $source->id]
            );

            if ($existing && in_array($existing->status, [
                ExternalListing::STATUS_PUBLISHED,
                ExternalListing::STATUS_DO_NOT_PUBLISH,
            ], true)) {
                $attributes['status'] = $existing->status;
                $attributes['published_at'] = $existing->status === ExternalListing::STATUS_PUBLISHED
                    ? ($existing->published_at ?? now())
                    : null;
            }

            /** @var ExternalListing $externalListing */
            $externalListing = ExternalListing::query()->updateOrCreate(
                [
                    'source_id' => $source->id,
                    'external_id' => $externalId,
                ],
                $attributes
            );

            SourceImportItem::query()->updateOrCreate(
                [
                    'source_import_id' => $import->id,
                    'external_id' => $externalId,
                ],
                [
                    'status' => $status === 'created'
                        ? SourceImportItem::STATUS_IMPORTED
                        : SourceImportItem::STATUS_UPDATED,
                    'payload' => $listing->rawPayload,
                    'normalized_payload' => $normalized,
                    'processed_at' => now(),
                ]
            );

            $this->syncDocuments($externalListing, $listing->rawPayload);
            $this->syncPriceEstimate($externalListing);
            $this->similarityService->persistTopSimilarities($externalListing);

            return [
                'status' => $status,
                'listing' => $externalListing,
            ];
        });
    }

    private function publishIfEligible(ExternalListing $listing): bool
    {
        if (in_array($listing->status, [
            ExternalListing::STATUS_DO_NOT_PUBLISH,
            ExternalListing::STATUS_PUBLISHED,
        ], true)) {
            return false;
        }

        if ($listing->status !== ExternalListing::STATUS_READY_FOR_REVIEW) {
            return false;
        }

        $listing->update([
            'status' => ExternalListing::STATUS_PUBLISHED,
            'published_at' => $listing->published_at ?? now(),
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function syncDocuments(ExternalListing $listing, array $rawPayload): void
    {
        if (!(bool) config('ecarstrade.import.publish_documents', true)) {
            return;
        }

        $documents = data_get($rawPayload, 'documents', data_get($rawPayload, 'media.documents', []));
        if (!is_array($documents) || $documents === []) {
            return;
        }

        ListingDocument::query()
            ->where('external_listing_id', $listing->id)
            ->delete();

        foreach ($documents as $document) {
            if (!is_array($document)) {
                continue;
            }

            $url = trim((string) ($document['url'] ?? $document['file_url'] ?? ''));
            if ($url === '') {
                continue;
            }

            ListingDocument::query()->create([
                'external_listing_id' => $listing->id,
                'document_type' => (string) ($document['type'] ?? 'other'),
                'title' => (string) ($document['title'] ?? ''),
                'file_url' => $url,
                'file_name' => (string) ($document['name'] ?? ''),
                'file_size' => is_numeric($document['size'] ?? null) ? (int) $document['size'] : null,
                'mime_type' => (string) ($document['mime_type'] ?? ''),
                'is_published' => true,
            ]);
        }
    }

    private function syncPriceEstimate(ExternalListing $listing): void
    {
        if ($listing->price_visible && $listing->price_amount !== null) {
            return;
        }

        $this->priceEstimator->estimate($listing);
    }

    private function registerItemError(SourceImport $import, EcarsTradeListingData $listing, string $message): void
    {
        $externalId = (string) ($listing->sourceRef ?: md5($listing->url));

        SourceImportItem::query()->updateOrCreate(
            [
                'source_import_id' => $import->id,
                'external_id' => $externalId,
            ],
            [
                'status' => SourceImportItem::STATUS_ERROR,
                'payload' => $listing->rawPayload,
                'error_message' => $message,
                'processed_at' => now(),
            ]
        );

        Log::warning('eCarsTrade import item error', [
            'source_import_id' => $import->id,
            'external_id' => $externalId,
            'message' => $message,
        ]);
    }
}
