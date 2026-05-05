<?php

namespace App\Http\Controllers;

use App\Jobs\SyncEcarsTradeListingsJob;
use App\Models\ExternalListing;
use App\Models\Source;
use App\Models\SourceImport;
use App\Models\User;
use App\Services\Imports\EcarsTrade\EcarsTradeImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEcarsTradeImportController extends Controller
{
    public function index(EcarsTradeImporter $importer): JsonResponse
    {
        $source = $importer->ensureSource();
        $latestImport = SourceImport::query()
            ->where('source_id', $source->id)
            ->latest('id')
            ->with('triggeredBy:id,email,name')
            ->first();

        $listings = ExternalListing::query()->where('source_id', $source->id);

        return response()->json([
            'data' => [
                'source' => [
                    'id' => $source->id,
                    'code' => $source->code,
                    'name' => $source->name,
                    'is_active' => (bool) $source->is_active,
                    'base_url' => $source->base_url,
                    'rights_media' => (bool) data_get($source->meta, 'rights_media', true),
                    'rights_documents' => (bool) data_get($source->meta, 'rights_documents', true),
                    'publish_media' => (bool) data_get($source->meta, 'publish_media', true),
                    'publish_documents' => (bool) data_get($source->meta, 'publish_documents', true),
                ],
                'latest_import' => $latestImport ? [
                    'id' => $latestImport->id,
                    'status' => $latestImport->status,
                    'sync_limit' => $latestImport->sync_limit,
                    'fetched_count' => $latestImport->fetched_count,
                    'created_count' => $latestImport->created_count,
                    'updated_count' => $latestImport->updated_count,
                    'error_count' => $latestImport->error_count,
                    'started_at' => optional($latestImport->started_at)->toIso8601String(),
                    'finished_at' => optional($latestImport->finished_at)->toIso8601String(),
                    'triggered_by' => $latestImport->triggeredBy ? [
                        'id' => $latestImport->triggeredBy->id,
                        'name' => $latestImport->triggeredBy->name,
                        'email' => $latestImport->triggeredBy->email,
                    ] : null,
                ] : null,
                'stats' => [
                    'total_listings' => (clone $listings)->count(),
                    'draft' => (clone $listings)->where('status', ExternalListing::STATUS_DRAFT)->count(),
                    'ready_for_review' => (clone $listings)->where('status', ExternalListing::STATUS_READY_FOR_REVIEW)->count(),
                    'published' => (clone $listings)->where('status', ExternalListing::STATUS_PUBLISHED)->count(),
                    'do_not_publish' => (clone $listings)->where('status', ExternalListing::STATUS_DO_NOT_PUBLISH)->count(),
                ],
                'recent_listings' => ExternalListing::query()
                    ->where('source_id', $source->id)
                    ->latest('updated_at')
                    ->limit(20)
                    ->get([
                        'id',
                        'title',
                        'make',
                        'model',
                        'year',
                        'price_visible',
                        'price_amount',
                        'status',
                        'listing_type',
                        'updated_at',
                    ])
                    ->map(fn (ExternalListing $listing) => [
                        'id' => $listing->id,
                        'title' => $listing->title,
                        'make' => $listing->make,
                        'model' => $listing->model,
                        'year' => $listing->year,
                        'price_visible' => (bool) $listing->price_visible,
                        'price_amount' => $listing->price_amount,
                        'status' => $listing->status,
                        'listing_type' => $listing->listing_type,
                        'updated_at' => optional($listing->updated_at)->toIso8601String(),
                    ])
                    ->values(),
            ],
        ]);
    }

    public function run(Request $request, EcarsTradeImporter $importer): JsonResponse
    {
        $limit = max(1, min(200, (int) $request->input('limit', config('ecarstrade.import.sync_limit', 20))));

        /** @var User $user */
        $user = $request->user();

        $import = $importer->run($user, $limit);

        return response()->json([
            'message' => 'Import eCarsTrade termine.',
            'data' => [
                'import_id' => $import->id,
                'status' => $import->status,
                'fetched_count' => $import->fetched_count,
                'created_count' => $import->created_count,
                'updated_count' => $import->updated_count,
                'error_count' => $import->error_count,
            ],
        ]);
    }

    public function enqueue(Request $request): JsonResponse
    {
        $limit = max(1, min(200, (int) $request->input('limit', config('ecarstrade.import.sync_limit', 20))));
        SyncEcarsTradeListingsJob::dispatch($limit);

        return response()->json([
            'message' => 'Import eCarsTrade planifie.',
            'data' => ['limit' => $limit],
        ]);
    }

    public function setPublishStatus(ExternalListing $listing, string $status): JsonResponse
    {
        if (!in_array($status, [ExternalListing::STATUS_PUBLISHED, ExternalListing::STATUS_DO_NOT_PUBLISH], true)) {
            return response()->json(['message' => 'Statut invalide.'], 422);
        }

        $listing->update([
            'status' => $status,
            'published_at' => $status === ExternalListing::STATUS_PUBLISHED ? now() : null,
        ]);

        return response()->json([
            'message' => $status === ExternalListing::STATUS_PUBLISHED
                ? 'Annonce publiee.'
                : 'Annonce marquee non publiable.',
            'data' => [
                'id' => $listing->id,
                'status' => $listing->status,
            ],
        ]);
    }

    public function publish(ExternalListing $listing): JsonResponse
    {
        return $this->setPublishStatus($listing, ExternalListing::STATUS_PUBLISHED);
    }

    public function doNotPublish(ExternalListing $listing): JsonResponse
    {
        return $this->setPublishStatus($listing, ExternalListing::STATUS_DO_NOT_PUBLISH);
    }
}
