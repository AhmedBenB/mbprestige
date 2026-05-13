<?php

namespace App\Jobs;

use App\DataTransferObjects\SearchCriteriaData;
use App\Models\CustomerSearch;
use App\Models\SearchResult;
use App\Models\SearchRun;
use App\Services\EcarsTradeSearchService;
use App\Services\SearchMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunCustomerSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly CustomerSearch $search,
    ) {}

    public function handle(
        EcarsTradeSearchService $searchService,
        SearchMatchingService $matchingService,
    ): void {
        $criteria = SearchCriteriaData::fromModel($this->search);

        $run = SearchRun::create([
            'customer_search_id' => $this->search->id,
            'source' => 'ecarstrade',
            'zone' => $this->search->source_zone,
            'status' => SearchRun::STATUS_RUNNING,
            'query_payload' => $criteria->toConnectorPayload(),
            'started_at' => now(),
        ]);

        $runtimeContextSnapshot = config('ecarstrade.runtime_context');
        $runtimeContext = array_merge(
            is_array($runtimeContextSnapshot) ? $runtimeContextSnapshot : [],
            [
                'trigger' => 'admin_search_run',
                'search_id' => $this->search->id,
                'search_run_id' => $run->id,
                'organization_id' => $this->search->organization_id,
                'source_zone' => $this->search->source_zone,
            ]
        );

        config(['ecarstrade.runtime_context' => $runtimeContext]);

        Log::info('eCarsTrade admin run started', $runtimeContext);

        try {
            $rawListings = $searchService->execute($this->search);
            $matches = $matchingService->filter($rawListings, $criteria);

            foreach ($matches as $listing) {
                $score = $matchingService->score($listing, $criteria);

                SearchResult::create([
                    'customer_search_id' => $this->search->id,
                    'search_run_id' => $run->id,
                    'source_ref' => $listing->sourceRef,
                    'listing_url' => $listing->url,
                    'title' => $listing->title,
                    'make' => $listing->make,
                    'model' => $listing->model,
                    'price' => $listing->price,
                    'year' => $listing->year,
                    'fuel' => $listing->fuel,
                    'gearbox' => $listing->gearbox,
                    'mileage' => $listing->mileage,
                    'color' => $listing->color,
                    'match_score' => $score,
                    'match_status' => SearchResult::STATUS_CANDIDATE,
                    'raw_payload' => $listing->rawPayload,
                ]);
            }

            $run->update([
                'status' => SearchRun::STATUS_COMPLETED,
                'result_count' => count($matches),
                'finished_at' => now(),
            ]);

            $this->search->update([
                'last_run_at' => now(),
            ]);

            Log::info('eCarsTrade admin run completed', array_merge($runtimeContext, [
                'raw_count' => count($rawListings),
                'match_count' => count($matches),
            ]));
        } catch (Throwable $exception) {
            $run->update([
                'status' => SearchRun::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            Log::warning('eCarsTrade admin run failed', array_merge($runtimeContext, [
                'message' => $exception->getMessage(),
            ]));

            report($exception);
        } finally {
            config(['ecarstrade.runtime_context' => $runtimeContextSnapshot]);
        }
    }
}
