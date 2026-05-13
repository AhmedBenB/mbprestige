<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientSearchRequest;
use App\Http\Requests\UpdateClientSearchRequest;
use App\Models\CustomerSearch;
use App\Models\SearchResult;
use App\Models\SearchRun;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ClientAccountService;
use App\Services\CustomerSearchDistributionService;
use App\Services\SearchResultFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('viewAny', CustomerSearch::class);

        $searches = CustomerSearch::query()
            ->where('user_id', $user->id)
            ->whereNull('parent_search_id')
            ->latest('created_at')
            ->paginate(min($request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $searches->getCollection()
                ->map(fn (CustomerSearch $search) => $this->searchListResource(
                    $this->loadClientFacingSearch($search, 1, 100)
                ))
                ->values(),
            'meta' => [
                'total' => $searches->total(),
                'current_page' => $searches->currentPage(),
                'last_page' => $searches->lastPage(),
            ],
        ]);
    }

    public function store(
        StoreClientSearchRequest $request,
        AuditLogService $auditLogService,
        CustomerSearchDistributionService $distributionService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('create', CustomerSearch::class);

        $payload = $request->validated();
        $criteria = $payload['criteria'];
        $clientAccount = $this->syncClientAccountFromPayload($user, $payload);

        $search = $distributionService->createRootSearch([
            'created_by' => $clientAccount->id,
            'user_id' => $clientAccount->id,
            'client_name' => $clientAccount->name,
            'client_first_name' => $clientAccount->first_name,
            'client_last_name' => $clientAccount->last_name,
            'client_email' => $clientAccount->email,
            'client_phone' => $clientAccount->phone,
            'client_comment' => $payload['comment'] ?? null,
            'consent_email' => (bool) $payload['consent_email'],
            'consent_sms' => (bool) $payload['consent_sms'],
            'manage_token' => Str::random(40),
            'unsubscribe_token' => Str::random(40),
            'make' => $criteria['make'],
            'model' => $criteria['model'] ?? null,
            'budget_max' => $criteria['budget_max'],
            'year_min' => $criteria['year_min'],
            'fuel' => $criteria['fuel'] ?? null,
            'transmission' => $criteria['transmission'] ?? null,
            'mileage_max' => $criteria['mileage_max'] ?? null,
            'mileage_tolerance' => 10000,
            'color' => $criteria['color'] ?? null,
            'source_zone' => 'all_cars',
            'status' => CustomerSearch::STATUS_ACTIVE,
        ]);

        $distribution = $distributionService->dispatchSelectedOrganizations($search, true);
        $distributionService->sendConfirmation($search);
        $search = $this->loadClientFacingSearch($search);

        $auditLogService->record(
            'client.search.created',
            $clientAccount,
            [
                'organization_id' => null,
                'criteria' => $criteria,
                'distributed_queued' => $distribution['queued'],
                'distributed_skipped' => $distribution['skipped'],
            ],
            $search,
            request: $request,
        );

        return response()->json([
            'message' => 'Votre nouvelle demande a bien ete enregistree.',
            'data' => [
                'search' => $this->searchDetailResource($search),
                'results' => $this->formatVisibleResults($search),
                'recent_runs' => $search->runs
                    ->map(fn (SearchRun $run) => $this->runResource($run))
                    ->values(),
            ],
        ], 201);
    }

    public function show(Request $request, CustomerSearch $search): JsonResponse
    {
        $this->authorize('view', $search);
        $search = $this->loadClientFacingSearch($search);

        return response()->json([
            'data' => [
                'search' => $this->searchDetailResource($search),
                'results' => $this->formatVisibleResults($search),
                'recent_runs' => $search->runs
                    ->map(fn (SearchRun $run) => $this->runResource($run))
                    ->values(),
            ],
        ]);
    }

    public function results(Request $request, CustomerSearch $search): JsonResponse
    {
        $this->authorize('view', $search);
        $search = $this->loadClientFacingSearch($search, 10, 50);

        return response()->json([
            'data' => $this->formatVisibleResults($search),
            'meta' => [
                'search_id' => $search->id,
                'count' => $search->results->count(),
            ],
        ]);
    }

    public function update(
        UpdateClientSearchRequest $request,
        CustomerSearch $search,
        AuditLogService $auditLogService,
        CustomerSearchDistributionService $distributionService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('update', $search);

        $payload = $request->validated();
        $criteria = $payload['criteria'] ?? [];
        $clientAccount = $this->syncClientAccountFromPayload($user, $payload, $search);
        $firstName = $clientAccount->first_name;
        $lastName = $clientAccount->last_name;

        $search = $distributionService->updateRootSearch($search, [
            'user_id' => $clientAccount?->id ?? $search->user_id,
            'client_name' => trim(implode(' ', array_filter([$firstName, $lastName]))),
            'client_first_name' => $firstName,
            'client_last_name' => $lastName,
            'client_email' => $clientAccount?->email ?? $search->client_email,
            'client_phone' => $clientAccount?->phone ?? $search->client_phone,
            'client_comment' => $payload['comment'] ?? $search->client_comment,
            'consent_email' => array_key_exists('consent_email', $payload) ? (bool) $payload['consent_email'] : $search->consent_email,
            'consent_sms' => array_key_exists('consent_sms', $payload) ? (bool) $payload['consent_sms'] : $search->consent_sms,
            'make' => $criteria['make'] ?? $search->make,
            'model' => array_key_exists('model', $criteria) ? ($criteria['model'] ?: null) : $search->model,
            'budget_max' => $criteria['budget_max'] ?? $search->budget_max,
            'year_min' => $criteria['year_min'] ?? $search->year_min,
            'fuel' => array_key_exists('fuel', $criteria) ? ($criteria['fuel'] ?: null) : $search->fuel,
            'transmission' => array_key_exists('transmission', $criteria) ? ($criteria['transmission'] ?: null) : $search->transmission,
            'mileage_max' => array_key_exists('mileage_max', $criteria) ? $criteria['mileage_max'] : $search->mileage_max,
            'color' => array_key_exists('color', $criteria) ? ($criteria['color'] ?: null) : $search->color,
        ]);

        $distribution = $distributionService->dispatchSelectedOrganizations($search, true);
        $distributionService->sendConfirmation($search);

        $auditLogService->record(
            'client.search.updated',
            $clientAccount,
            [
                'organization_id' => null,
                'search_id' => $search->id,
                'distributed_queued' => $distribution['queued'],
                'distributed_skipped' => $distribution['skipped'],
            ],
            $search,
            request: $request,
        );

        $search = $this->loadClientFacingSearch($search);

        return response()->json([
            'message' => 'Demande client mise a jour.',
            'data' => [
                'search' => $this->searchDetailResource($search),
                'results' => $this->formatVisibleResults($search),
            ],
        ]);
    }

    private function syncClientAccountFromPayload(
        User $user,
        array $payload,
        ?CustomerSearch $search = null,
    ): User {
        return app(ClientAccountService::class)->resolveOrCreate(
            $user->email,
            array_key_exists('first_name', $payload)
                ? trim((string) $payload['first_name']) ?: null
                : ($search?->client_first_name ?? $user->first_name),
            array_key_exists('last_name', $payload)
                ? trim((string) $payload['last_name']) ?: null
                : ($search?->client_last_name ?? $user->last_name),
            array_key_exists('phone', $payload)
                ? ($payload['phone'] ?: null)
                : ($search?->client_phone ?? $user->phone),
            null,
        );
    }

    private function loadClientFacingSearch(
        CustomerSearch $search,
        int $runLimit = 10,
        int $resultLimit = 20,
    ): CustomerSearch {
        $distributionService = app(CustomerSearchDistributionService::class);
        $runs = $distributionService->recentRuns($search, $runLimit);
        $results = $distributionService->visibleResults($search, $resultLimit);
        $latestRun = $runs->first() ?? $distributionService->latestRun($search);

        $search->setRelation('runs', $runs);
        $search->setRelation('results', $results);
        $search->setRelation('latestRun', $latestRun);

        return $search;
    }

    private function searchListResource(CustomerSearch $search): array
    {
        $latestRun = $search->relationLoaded('latestRun') ? $search->latestRun : null;

        return [
            'id' => $search->id,
            'status' => $search->status,
            'created_at' => optional($search->created_at)->toIso8601String(),
            'last_run_at' => optional($search->last_run_at)->toIso8601String(),
            'progress_label' => $this->progressLabel($search, $latestRun),
            'visible_results_count' => $search->relationLoaded('results') ? $search->results->count() : 0,
            'criteria' => $this->criteriaResource($search),
        ];
    }

    private function searchDetailResource(CustomerSearch $search): array
    {
        $latestRun = $search->runs->firstWhere('status', SearchRun::STATUS_RUNNING)
            ?? $search->runs->first();

        return [
            'id' => $search->id,
            'status' => $search->status,
            'created_at' => optional($search->created_at)->toIso8601String(),
            'last_run_at' => optional($search->last_run_at)->toIso8601String(),
            'progress_label' => $this->progressLabel($search, $latestRun),
            'client' => [
                'first_name' => $search->client_first_name,
                'last_name' => $search->client_last_name,
                'full_name' => $search->client_full_name,
                'email' => $search->client_email,
                'phone' => $search->client_phone,
                'comment' => $search->client_comment,
                'consent_email' => (bool) $search->consent_email,
                'consent_sms' => (bool) $search->consent_sms,
            ],
            'criteria' => $this->criteriaResource($search),
        ];
    }

    private function criteriaResource(CustomerSearch $search): array
    {
        return [
            'make' => $search->make,
            'model' => $search->model,
            'budget_max' => $search->budget_max,
            'year_min' => $search->year_min,
            'fuel' => $search->fuel,
            'transmission' => $search->transmission,
            'mileage_max' => $search->mileage_max,
            'color' => $search->color,
        ];
    }

    private function formatVisibleResults(CustomerSearch $search): array
    {
        $formatter = app(SearchResultFormatter::class);

        return $search->results
            ->map(function (SearchResult $result) use ($formatter) {
                $formatted = $formatter->format($result);

                return [
                    'id' => $result->id,
                    'title' => $formatted['title'],
                    'details' => $formatted['details'],
                    'url' => $formatted['url'],
                    'price' => $formatted['price'],
                    'decision' => $formatted['decision'],
                    'score' => $formatted['score'],
                    'shared_at' => optional($result->shared_with_client_at)->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function progressLabel(CustomerSearch $search, ?SearchRun $latestRun): string
    {
        $visibleResults = $search->results
            ->whereIn('match_status', [SearchResult::STATUS_APPROVED, SearchResult::STATUS_SHARED])
            ->count();

        if ($visibleResults > 0) {
            return 'Des resultats ont ete selectionnes pour vous.';
        }

        if ($latestRun?->status === SearchRun::STATUS_RUNNING || $latestRun?->status === SearchRun::STATUS_PENDING) {
            return 'La recherche est en cours.';
        }

        if ($latestRun?->status === SearchRun::STATUS_FAILED) {
            return 'Votre demande est bien prise en compte. Nous finalisons une verification manuelle avant de revenir vers vous.';
        }

        return 'Votre demande a bien ete enregistree.';
    }

    private function publicRunMessage(SearchRun $run): ?string
    {
        return match ($run->status) {
            SearchRun::STATUS_FAILED => 'Une verification complementaire est en cours par notre equipe.',
            SearchRun::STATUS_RUNNING, SearchRun::STATUS_PENDING => 'Recherche en cours sur vos criteres.',
            SearchRun::STATUS_COMPLETED => $run->result_count > 0
                ? 'Des opportunites ont ete analysees pour cette recherche.'
                : 'Aucune opportunite retenue lors de ce passage.',
            default => null,
        };
    }

    private function runResource(SearchRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'result_count' => $run->result_count,
            'public_message' => $this->publicRunMessage($run),
            'started_at' => optional($run->started_at)->toIso8601String(),
            'finished_at' => optional($run->finished_at)->toIso8601String(),
            'created_at' => optional($run->created_at)->toIso8601String(),
        ];
    }
}
