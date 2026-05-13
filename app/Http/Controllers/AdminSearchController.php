<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerSearchRequest;
use App\Http\Requests\UpdateCustomerSearchRequest;
use App\Http\Requests\UpdateSearchResultRequest;
use App\Jobs\RunCustomerSearchJob;
use App\Models\CustomerSearch;
use App\Models\SearchResult;
use App\Models\SearchRun;
use App\Models\User;
use App\Services\AdminSettingsService;
use App\Services\ClientAccountService;
use App\Services\CustomerSearchDistributionService;
use App\Services\OrganizationEcarsTradeAccountService;
use App\Services\SearchResultFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerSearch::class);

        $query = $this->searchQueryForUser($request->user())
            ->withCount('results')
            ->with('latestRun')
            ->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $needle = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->where('client_name', 'like', "%{$needle}%")
                    ->orWhere('client_email', 'like', "%{$needle}%")
                    ->orWhere('make', 'like', "%{$needle}%")
                    ->orWhere('model', 'like', "%{$needle}%");
            });
        }

        $searches = $query->paginate(min($request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $searches->getCollection()
                ->map(fn (CustomerSearch $search) => $this->searchResource($this->hydrateSearchSummary($search)))
                ->values(),
            'meta' => [
                'total' => $searches->total(),
                'current_page' => $searches->currentPage(),
                'last_page' => $searches->lastPage(),
            ],
        ]);
    }

    public function store(StoreCustomerSearchRequest $request): JsonResponse
    {
        $this->authorize('create', CustomerSearch::class);

        $payload = $request->validated();
        [$firstName, $lastName, $fullName] = $this->normalizeAdminNamePayload($payload);
        /** @var User $admin */
        $admin = $request->user();
        $clientAccount = $this->resolveClientAccountFromPayload($payload, $firstName, $lastName);
        $organizationId = $this->resolveOrganizationForAdminPayload($admin, $clientAccount);

        $search = CustomerSearch::create([
            ...$payload,
            'created_by' => $request->user()->id,
            'organization_id' => $organizationId,
            'user_id' => $clientAccount?->id,
            'client_name' => $fullName,
            'client_first_name' => $firstName,
            'client_last_name' => $lastName,
            'mileage_tolerance' => $request->integer('mileage_tolerance', 10000),
            'source_zone' => $request->input('source_zone', 'all_cars'),
            'status' => $request->input('status', CustomerSearch::STATUS_ACTIVE),
        ]);

        $search = $this->hydrateSearchSummary($search);

        return response()->json([
            'message' => 'Recherche client créée.',
            'data' => $this->searchResource($search),
        ], 201);
    }

    public function show(CustomerSearch $search): JsonResponse
    {
        $this->authorize('view', $search);
        $search = $this->hydrateSearchSummary($search);
        $search->setRelation('runs', $this->runsForSearch($search, 20));

        $matches = $this->visibleSortedMatches($this->resultsForSearch($search), $search)
            ->take(20)
            ->values();

        return response()->json([
            'data' => [
                ...$this->searchResource($search),
                'runs' => $search->runs->map(fn (SearchRun $run) => $this->runResource($run))->values(),
                'recent_matches' => $matches->map(fn (SearchResult $result) => $this->matchListResource($result))->values(),
            ],
        ]);
    }

    public function update(UpdateCustomerSearchRequest $request, CustomerSearch $search): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $this->authorize('update', $search);
        $payload = $request->validated();
        [$firstName, $lastName, $fullName] = $this->normalizeAdminNamePayload($payload + [
            'client_name' => $search->client_name,
            'client_first_name' => $search->client_first_name,
            'client_last_name' => $search->client_last_name,
        ]);
        $clientAccount = $this->resolveClientAccountFromPayload($payload + [
            'client_email' => $search->client_email,
            'client_phone' => $search->client_phone,
        ], $firstName, $lastName);
        $organizationId = $this->resolveOrganizationForAdminPayload($admin, $clientAccount, $search);

        $search->fill([
            ...$payload,
            'organization_id' => $organizationId,
            'user_id' => array_key_exists('client_email', $payload)
                ? $clientAccount?->id
                : $search->user_id,
            'client_name' => $fullName,
            'client_first_name' => $firstName,
            'client_last_name' => $lastName,
        ]);
        $search->save();
        $search = $this->hydrateSearchSummary($search);

        return response()->json([
            'message' => 'Recherche client mise à jour.',
            'data' => $this->searchResource($search),
        ]);
    }

    public function destroy(CustomerSearch $search): JsonResponse
    {
        $this->authorize('delete', $search);
        $search->delete();

        return response()->json([
            'message' => 'Recherche client supprimée.',
        ]);
    }

    public function pause(CustomerSearch $search): JsonResponse
    {
        $this->authorize('run', $search);
        $search->update(['status' => CustomerSearch::STATUS_PAUSED]);

        return response()->json([
            'message' => 'Recherche mise en pause.',
            'data' => $this->searchResource($this->hydrateSearchSummary($search->fresh())),
        ]);
    }

    public function resume(CustomerSearch $search): JsonResponse
    {
        $this->authorize('run', $search);
        $search->update(['status' => CustomerSearch::STATUS_ACTIVE]);

        return response()->json([
            'message' => 'Recherche réactivée.',
            'data' => $this->searchResource($this->hydrateSearchSummary($search->fresh())),
        ]);
    }

    public function close(CustomerSearch $search): JsonResponse
    {
        $this->authorize('run', $search);
        $search->update(['status' => CustomerSearch::STATUS_COMPLETED]);

        return response()->json([
            'message' => 'Recherche clôturée.',
            'data' => $this->searchResource($this->hydrateSearchSummary($search->fresh())),
        ]);
    }

    public function run(
        CustomerSearch $search,
        OrganizationEcarsTradeAccountService $organizationEcarsTradeAccountService,
        CustomerSearchDistributionService $distributionService,
    ): JsonResponse
    {
        $this->authorize('run', $search);

        if ($search->isDistributedRoot()) {
            $distribution = $distributionService->dispatchSelectedOrganizations($search, false);

            return response()->json([
                'message' => 'Recherche centrale lancee sur les garages selectionnes.',
                'data' => [
                    'search_id' => $search->id,
                    'queued' => $distribution['queued'] > 0,
                    'count' => $distribution['queued'],
                    'skipped_count' => $distribution['skipped'],
                ],
            ]);
        }

        if ($message = $organizationEcarsTradeAccountService->readinessMessageForSearch($search)) {
            return response()->json([
                'message' => $message,
            ], 422);
        }

        RunCustomerSearchJob::dispatch($search);

        return response()->json([
            'message' => 'Recherche lancée.',
            'data' => [
                'search_id' => $search->id,
                'queued' => true,
            ],
        ]);
    }

    public function runAll(
        Request $request,
        OrganizationEcarsTradeAccountService $organizationEcarsTradeAccountService,
        CustomerSearchDistributionService $distributionService,
    ): JsonResponse
    {
        $this->authorize('runAll', CustomerSearch::class);

        $searches = $this->searchQueryForUser($request->user())
            ->where('status', CustomerSearch::STATUS_ACTIVE)
            ->get();

        $queued = 0;
        $skipped = 0;

        foreach ($searches as $search) {
            if ($search->isDistributedRoot()) {
                $distribution = $distributionService->dispatchSelectedOrganizations($search, false);
                $queued += $distribution['queued'];
                $skipped += $distribution['skipped'];
                continue;
            }

            if ($organizationEcarsTradeAccountService->readinessMessageForSearch($search) !== null) {
                $skipped++;
                continue;
            }

            RunCustomerSearchJob::dispatch($search);
            $queued++;
        }

        if ($searches->isNotEmpty() && $queued === 0) {
            return response()->json([
                'message' => 'Aucune recherche ne peut etre lancee tant que le compte eCarsTrade du garage n est pas configure.',
            ], 422);
        }

        return response()->json([
            'message' => 'Scan manuel global lancé.',
            'data' => [
                'queued' => $queued > 0,
                'count' => $queued,
                'skipped_count' => $skipped,
            ],
        ]);
    }

    public function results(CustomerSearch $search, SearchResultFormatter $formatter): JsonResponse
    {
        $this->authorize('view', $search);
        $settings = app(AdminSettingsService::class)->all();
        $results = $this->visibleSortedMatches($this->resultsForSearch($search), $search);

        return response()->json([
            'data' => $results->map(function (SearchResult $result) use ($formatter, $settings, $search) {
                $listingType = $this->resolveListingType($result);
                $budgetGap = $this->resolveBudgetGap($result, $search);

                return [
                    ...$formatter->format($result),
                    ...$this->pricingSummary($result, $settings),
                    'decision' => $result->match_status,
                    'score' => $result->match_score,
                    'listing_type' => $listingType,
                    'listing_type_label' => $this->listingTypeLabel($listingType),
                    'budget_gap' => $budgetGap,
                    'budget_gap_label' => $this->formatBudgetGap($budgetGap),
                ];
            })->values(),
            'meta' => [
                'count' => $results->count(),
                'search_id' => $search->id,
            ],
        ]);
    }

    public function matches(Request $request, CustomerSearch $search): JsonResponse
    {
        $this->authorize('view', $search);
        $settings = app(AdminSettingsService::class)->all();
        $matches = $this->visibleSortedMatches(
            $this->resultsQueryForSearch($search)
                ->when($request->filled('decision'), fn ($query) => $query->where('match_status', $request->string('decision')))
                ->get(),
            $search
        );

        return response()->json([
            'data' => $matches
                ->map(fn (SearchResult $result) => $this->matchListResource($result, $settings))
                ->values(),
            'meta' => [
                'search_id' => $search->id,
            ],
        ]);
    }

    public function allMatches(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SearchResult::class);

        $settings = app(AdminSettingsService::class)->all();
        $query = $this->resultQueryForUser($request->user())
            ->with('search');

        if ($request->filled('decision')) {
            $query->where('match_status', $request->string('decision'));
        }

        if ($request->filled('search_id')) {
            $query->where('customer_search_id', $request->integer('search_id'));
        }

        $perPage = min($request->integer('per_page', 20), 100);
        $page = max($request->integer('page', 1), 1);
        $matches = $this->visibleSortedMatches($query->get());
        $total = $matches->count();
        $pageItems = $matches->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $pageItems->map(fn (SearchResult $result) => $this->matchListResource($result, $settings))->values(),
            'meta' => [
                'total' => $total,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
            ],
        ]);
    }

    public function showMatch(SearchResult $result, AdminSettingsService $settingsService): JsonResponse
    {
        $this->authorize('view', $result);
        $result->load('search');

        return response()->json([
            'data' => $this->matchDetailResource($result, $settingsService->all()),
        ]);
    }

    public function updateMatchStatus(UpdateSearchResultRequest $request, SearchResult $result, AdminSettingsService $settingsService): JsonResponse
    {
        $this->authorize('update', $result);
        $payload = $request->validated();

        if (array_key_exists('decision', $payload)) {
            $result->match_status = $payload['decision'];
            $result->reviewed_at = now();
        }

        if (array_key_exists('admin_summary', $payload)) {
            $result->admin_summary = $payload['admin_summary'];
        }

        if (array_key_exists('review_notes', $payload)) {
            $result->review_notes = $payload['review_notes'];
        }

        if (($payload['decision'] ?? null) === SearchResult::STATUS_SHARED) {
            $result->shared_with_client_at = now();
        }

        if (array_key_exists('shared_channel', $payload)) {
            $result->shared_channel = $payload['shared_channel'];
        }

        if (array_key_exists('shared_note', $payload)) {
            $result->shared_note = $payload['shared_note'];
        }

        $result->save();
        $result->load('search');

        return response()->json([
            'message' => 'Match mis à jour.',
            'data' => $this->matchDetailResource($result, $settingsService->all()),
        ]);
    }

    public function approve(SearchResult $result, Request $request, AdminSettingsService $settingsService): JsonResponse
    {
        return $this->updateMatchStatusFromAction($result, SearchResult::STATUS_APPROVED, $request, $settingsService);
    }

    public function reject(SearchResult $result, Request $request, AdminSettingsService $settingsService): JsonResponse
    {
        return $this->updateMatchStatusFromAction($result, SearchResult::STATUS_REJECTED, $request, $settingsService);
    }

    public function hold(SearchResult $result, Request $request, AdminSettingsService $settingsService): JsonResponse
    {
        return $this->updateMatchStatusFromAction($result, SearchResult::STATUS_ON_HOLD, $request, $settingsService);
    }

    public function markShared(SearchResult $result, Request $request, AdminSettingsService $settingsService): JsonResponse
    {
        return $this->updateMatchStatusFromAction($result, SearchResult::STATUS_SHARED, $request, $settingsService);
    }

    private function updateMatchStatusFromAction(
        SearchResult $result,
        string $decision,
        Request $request,
        AdminSettingsService $settingsService,
    ): JsonResponse {
        $this->authorize('update', $result);
        $result->update([
            'match_status' => $decision,
            'admin_summary' => $request->input('admin_summary', $result->admin_summary),
            'review_notes' => $request->input('review_notes', $result->review_notes),
            'reviewed_at' => now(),
            'shared_with_client_at' => $decision === SearchResult::STATUS_SHARED ? now() : $result->shared_with_client_at,
            'shared_channel' => $request->input('shared_channel', $result->shared_channel),
            'shared_note' => $request->input('shared_note', $result->shared_note),
        ]);

        $result->load('search');

        return response()->json([
            'message' => 'Match mis à jour.',
            'data' => $this->matchDetailResource($result, $settingsService->all()),
        ]);
    }

    private function searchResource(CustomerSearch $search): array
    {
        $latestRun = $search->relationLoaded('latestRun') ? $search->latestRun : null;

        return [
            'id' => $search->id,
            'status' => $search->status,
            'client_name' => $search->client_full_name,
            'client_first_name' => $search->client_first_name,
            'client_last_name' => $search->client_last_name,
            'client_email' => $search->client_email,
            'client_phone' => $search->client_phone,
            'client_comment' => $search->client_comment,
            'client_account' => [
                'user_id' => $search->user_id,
                'has_account' => $search->user_id !== null,
            ],
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
            'criteria' => [
                'make' => $search->make,
                'model' => $search->model,
                'budget_max' => $search->budget_max,
                'year_min' => $search->year_min,
                'fuel' => $search->fuel,
                'transmission' => $search->transmission,
                'mileage_max' => $search->mileage_max,
                'mileage_tolerance' => $search->mileage_tolerance,
                'color' => $search->color,
                'source_zone' => $search->source_zone,
            ],
            'criteria_summary' => $search->criteria_summary,
            'budget_max' => $search->budget_max,
            'results_count' => $search->results_count ?? 0,
            'last_run_at' => optional($search->last_run_at)->toIso8601String(),
            'latest_run' => $latestRun ? $this->runResource($latestRun) : null,
            'created_at' => optional($search->created_at)->toIso8601String(),
        ];
    }

    private function runResource(SearchRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'source' => $run->source,
            'zone' => $run->zone,
            'result_count' => $run->result_count,
            'error_message' => $run->error_message,
            'started_at' => optional($run->started_at)->toIso8601String(),
            'finished_at' => optional($run->finished_at)->toIso8601String(),
            'created_at' => optional($run->created_at)->toIso8601String(),
        ];
    }

    private function matchListResource(SearchResult $result, ?array $settings = null): array
    {
        $settings ??= app(AdminSettingsService::class)->all();
        $search = $result->relationLoaded('search') ? $result->search : $result->search;
        $pricing = $this->pricingSummary($result, $settings);
        $listingType = $this->resolveListingType($result);
        $budgetGap = $this->resolveBudgetGap($result, $search);
        $countryOrigin = data_get($result->raw_payload, 'country_origin')
            ?? data_get($result->raw_payload, 'api_data.country_origin');

        return [
            'id' => $result->id,
            'search_id' => $result->customer_search_id,
            'decision' => $result->match_status,
            'score' => $result->match_score,
            'listing_type' => $listingType,
            'listing_type_label' => $this->listingTypeLabel($listingType),
            'budget_gap' => $budgetGap,
            'budget_gap_label' => $this->formatBudgetGap($budgetGap),
            'total_cost' => $pricing['total_cost'],
            'estimated_margin' => $pricing['estimated_margin'],
            'admin_summary' => $result->admin_summary,
            'review_notes' => $result->review_notes,
            'reviewed_at' => optional($result->reviewed_at)->toIso8601String(),
            'client_shared_at' => optional($result->shared_with_client_at)->toIso8601String(),
            'shared_channel' => $result->shared_channel,
            'listing' => [
                'id' => $result->id,
                'vehicle_label' => $result->title ?: trim(($result->make ?? '') . ' ' . ($result->model ?? '')),
                'source_name' => 'ecarstrade',
                'source_ref' => $result->source_ref,
                'source_url' => $result->listing_url,
                'make' => $result->make,
                'model' => $result->model,
                'version' => null,
                'price_buy' => $result->price,
                'year' => $result->year,
                'mileage' => $result->mileage,
                'fuel' => $result->fuel,
                'transmission' => $result->gearbox,
                'vehicle_condition' => 'unknown',
                'country_origin' => $countryOrigin,
                'source_type' => $listingType,
                'source_type_label' => $this->listingTypeLabel($listingType),
                'vat_status' => 'unknown',
                'coc_status' => 'unknown',
                'history_status' => 'unknown',
                'maintenance_book_status' => 'unknown',
                'color' => $result->color,
                'budget_gap' => $budgetGap,
                'budget_gap_label' => $this->formatBudgetGap($budgetGap),
            ],
            'client' => $search ? [
                'first_name' => $search->client_first_name,
                'last_name' => $search->client_last_name,
                'full_name' => $search->client_full_name,
                'email' => $search->client_email,
                'phone' => $search->client_phone,
                'comment' => $search->client_comment,
            ] : null,
        ];
    }

    private function matchDetailResource(SearchResult $result, array $settings): array
    {
        $resource = $this->matchListResource($result, $settings);
        $pricing = $this->pricingSummary($result, $settings);
        $search = $result->search;

        $resource['url'] = $result->listing_url;
        $resource['details'] = app(SearchResultFormatter::class)->format($result)['details'] ?? '';
        $resource['search_criteria'] = $search ? [
            'make' => $search->make,
            'model' => $search->model,
            'budget_max' => $search->budget_max,
            'year_min' => $search->year_min,
            'fuel' => $search->fuel,
            'transmission' => $search->transmission,
            'mileage_max' => $search->mileage_max,
            'color' => $search->color,
        ] : null;
        $resource['search_request'] = $search ? [
            'id' => $search->id,
            'status' => $search->status,
            'created_at' => optional($search->created_at)->toIso8601String(),
            'last_run_at' => optional($search->last_run_at)->toIso8601String(),
            'criteria_summary' => $search->criteria_summary,
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
            'criteria' => [
                'make' => $search->make,
                'model' => $search->model,
                'budget_max' => $search->budget_max,
                'year_min' => $search->year_min,
                'fuel' => $search->fuel,
                'transmission' => $search->transmission,
                'mileage_max' => $search->mileage_max,
                'color' => $search->color,
            ],
        ] : null;
        $resource['search_runs'] = $search
            ? $search->runs()->latest('created_at')->limit(10)->get()->map(fn (SearchRun $run) => $this->runResource($run))->values()
            : [];
        $resource['pricing'] = $pricing['pricing'];
        $resource['total_cost'] = $pricing['total_cost'];
        $resource['estimated_margin'] = $pricing['estimated_margin'];

        return $resource;
    }

    private function normalizeAdminNamePayload(array $payload): array
    {
        $firstName = trim((string) ($payload['client_first_name'] ?? ''));
        $lastName = trim((string) ($payload['client_last_name'] ?? ''));
        $fullName = trim((string) ($payload['client_name'] ?? ''));

        if ($firstName !== '' || $lastName !== '') {
            return [
                $firstName !== '' ? $firstName : null,
                $lastName !== '' ? $lastName : null,
                trim($firstName . ' ' . $lastName),
            ];
        }

        $parts = preg_split('/\s+/', $fullName, 2) ?: [];

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
            $fullName,
        ];
    }

    private function resolveClientAccountFromPayload(
        array $payload,
        ?string $firstName,
        ?string $lastName,
    ): ?\App\Models\User {
        return app(ClientAccountService::class)->resolveOrCreate(
            $payload['client_email'] ?? null,
            $firstName,
            $lastName,
            $payload['client_phone'] ?? null,
        );
    }

    /**
     * @param  Collection<int, SearchResult>  $matches
     * @return Collection<int, SearchResult>
     */
    private function visibleSortedMatches(Collection $matches, ?CustomerSearch $fallbackSearch = null): Collection
    {
        return $matches
            ->filter(fn (SearchResult $result) => $this->matchesBudgetRule($result, $fallbackSearch))
            ->sort(fn (SearchResult $left, SearchResult $right) => $this->compareMatches($left, $right, $fallbackSearch))
            ->values();
    }

    private function compareMatches(
        SearchResult $left,
        SearchResult $right,
        ?CustomerSearch $fallbackSearch = null,
    ): int {
        $typeComparison = $this->listingTypePriority($this->resolveListingType($left))
            <=> $this->listingTypePriority($this->resolveListingType($right));
        if ($typeComparison !== 0) {
            return $typeComparison;
        }

        $windowComparison = $this->preferredBudgetWindowPriority($left, $fallbackSearch)
            <=> $this->preferredBudgetWindowPriority($right, $fallbackSearch);
        if ($windowComparison !== 0) {
            return $windowComparison;
        }

        $scoreComparison = (int) ($right->match_score ?? 0) <=> (int) ($left->match_score ?? 0);
        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        $leftGap = $this->resolveBudgetGap($left, $fallbackSearch) ?? INF;
        $rightGap = $this->resolveBudgetGap($right, $fallbackSearch) ?? INF;
        $gapComparison = $leftGap <=> $rightGap;
        if ($gapComparison !== 0) {
            return $gapComparison;
        }

        $leftPrice = $left->price ?? INF;
        $rightPrice = $right->price ?? INF;
        $priceComparison = $leftPrice <=> $rightPrice;
        if ($priceComparison !== 0) {
            return $priceComparison;
        }

        return ($right->created_at?->timestamp ?? 0) <=> ($left->created_at?->timestamp ?? 0);
    }

    private function matchesBudgetRule(SearchResult $result, ?CustomerSearch $fallbackSearch = null): bool
    {
        $budgetGap = $this->resolveBudgetGap($result, $fallbackSearch);

        return $budgetGap !== null
            && $budgetGap >= 2000;
    }

    private function resolveBudgetGap(SearchResult $result, ?CustomerSearch $fallbackSearch = null): ?float
    {
        $search = $fallbackSearch
            ?? ($result->relationLoaded('search') ? $result->search : $result->search);

        if (!$search || $search->budget_max === null || $result->price === null) {
            return null;
        }

        return round((float) $search->budget_max - (float) $result->price, 2);
    }

    private function resolveListingType(SearchResult $result): string
    {
        $rawPayload = is_array($result->raw_payload) ? $result->raw_payload : [];
        $storedType = strtolower(trim((string) (
            data_get($rawPayload, 'listing_type')
            ?? data_get($rawPayload, 'source_type')
            ?? ''
        )));

        if (in_array($storedType, ['auction', 'fixed_price'], true)) {
            return $storedType;
        }

        $auctionType = strtolower(trim((string) (
            data_get($rawPayload, 'auction_type')
            ?? data_get($rawPayload, 'api_data.auction_type')
            ?? ''
        )));
        $visibleType = strtolower(trim((string) data_get($rawPayload, 'visible_type', '')));
        $liveAuction = trim((string) data_get($rawPayload, 'live_auction', ''));
        $signals = strtolower(trim(implode(' ', array_filter([
            (string) data_get($rawPayload, 'listing_type_label', ''),
            (string) data_get($rawPayload, 'price_text', ''),
            (string) data_get($rawPayload, 'card_html_excerpt', ''),
            (string) data_get($rawPayload, 'api_data.auction_title', ''),
            (string) data_get($rawPayload, 'api_data.label', ''),
            (string) $result->listing_url,
        ]))));

        if (
            $auctionType === 'stock'
            || $visibleType === 'close'
            || str_contains($signals, 'new price')
            || str_contains($signals, 'our stock')
            || str_contains($signals, 'prix fixe')
            || str_contains($signals, '/auctions/stock')
        ) {
            return 'fixed_price';
        }

        if ($liveAuction === '1' || $auctionType !== '') {
            return 'auction';
        }

        return 'auction';
    }

    private function listingTypePriority(string $listingType): int
    {
        return $listingType === 'fixed_price' ? 1 : 0;
    }

    private function preferredBudgetWindowPriority(
        SearchResult $result,
        ?CustomerSearch $fallbackSearch = null,
    ): int {
        $budgetGap = $this->resolveBudgetGap($result, $fallbackSearch);

        if ($budgetGap === null) {
            return 2;
        }

        return ($budgetGap >= 2000 && $budgetGap <= 3000) ? 0 : 1;
    }

    private function listingTypeLabel(string $listingType): string
    {
        return $listingType === 'fixed_price' ? 'Prix fixe' : 'Enchere';
    }

    private function formatBudgetGap(?float $budgetGap): ?string
    {
        if ($budgetGap === null) {
            return null;
        }

        return number_format($budgetGap, 0, ',', ' ') . ' EUR sous budget';
    }

    private function pricingSummary(SearchResult $result, array $settings): array
    {
        $pricingSettings = $settings['pricing'] ?? [];
        $thresholds = $settings['thresholds'] ?? [];

        $priceBuy = (float) ($result->price ?? 0);
        $platformFee = (float) ($pricingSettings['platform_fee'] ?? 0);
        $transportCost = (float) ($pricingSettings['transport_cost'] ?? 0);
        $prepCost = (float) ($pricingSettings['prep_cost'] ?? 0);
        $adminCost = (float) ($pricingSettings['admin_cost'] ?? 0);
        $warrantyReserve = (float) ($pricingSettings['warranty_reserve'] ?? 0);
        $safetyBuffer = (float) ($pricingSettings['safety_buffer'] ?? 0);
        $targetMargin = (float) ($thresholds['candidate_from_margin'] ?? 0);

        $totalCost = $priceBuy + $platformFee + $transportCost + $prepCost + $adminCost + $warrantyReserve + $safetyBuffer;
        $rawResaleEstimate = data_get($result->raw_payload, 'estimated_resale')
            ?? data_get($result->raw_payload, 'resale_estimate');
        $resaleEstimate = is_numeric($rawResaleEstimate)
            ? (float) $rawResaleEstimate
            : $totalCost + $targetMargin;
        $estimatedMargin = $resaleEstimate - $totalCost;

        return [
            'pricing' => [
                'platform_fee' => $platformFee,
                'transport_cost' => $transportCost,
                'prep_cost' => $prepCost,
                'admin_cost' => $adminCost,
                'warranty_reserve' => $warrantyReserve,
                'safety_buffer' => $safetyBuffer,
                'target_margin' => $targetMargin,
                'resale_estimate' => $resaleEstimate,
            ],
            'total_cost' => $totalCost,
            'estimated_margin' => $estimatedMargin,
        ];
    }

    private function hydrateSearchSummary(CustomerSearch $search): CustomerSearch
    {
        if (!$search->isDistributedRoot()) {
            $search->setAttribute(
                'results_count',
                $search->results_count ?? $search->results()->count()
            );

            if (!$search->relationLoaded('latestRun')) {
                $search->setRelation('latestRun', $search->latestRun()->first());
            }

            return $search;
        }

        $distributionService = app(CustomerSearchDistributionService::class);

        $search->setAttribute('results_count', $distributionService->visibleResultsCount($search));
        $search->setRelation('latestRun', $distributionService->latestRun($search));

        return $search;
    }

    /**
     * @return Collection<int, SearchResult>
     */
    private function resultsForSearch(CustomerSearch $search): Collection
    {
        if ($search->isDistributedRoot()) {
            return app(CustomerSearchDistributionService::class)->allResults($search);
        }

        return $search->results()->get();
    }

    /**
     * @return Collection<int, SearchRun>
     */
    private function runsForSearch(CustomerSearch $search, int $limit = 20): Collection
    {
        if ($search->isDistributedRoot()) {
            return app(CustomerSearchDistributionService::class)->recentRuns($search, $limit);
        }

        return $search->runs()
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    private function resultsQueryForSearch(CustomerSearch $search)
    {
        if ($search->isDistributedRoot()) {
            return app(CustomerSearchDistributionService::class)->resultsQuery($search);
        }

        return $search->results();
    }

    private function searchQueryForUser(User $user)
    {
        $query = CustomerSearch::query();

        if ($user->isPartnerAdmin()) {
            $query->where('organization_id', $user->organization_id);
        } else {
            $query->whereNull('parent_search_id');
        }

        return $query;
    }

    private function resultQueryForUser(User $user)
    {
        $query = SearchResult::query();

        if ($user->isPartnerAdmin()) {
            $query->whereHas('search', fn ($builder) => $builder->where('organization_id', $user->organization_id));
        }

        return $query;
    }

    private function resolveOrganizationForAdminPayload(
        User $admin,
        ?User $clientAccount,
        ?CustomerSearch $existingSearch = null,
    ): ?int {
        if ($admin->isSuperAdmin()) {
            return $existingSearch?->organization_id ?? $clientAccount?->organization_id;
        }

        abort_unless($admin->organization_id !== null, 422, 'Cet admin n est rattache a aucune organisation.');

        if (
            $clientAccount
            && $clientAccount->organization_id !== null
            && (int) $clientAccount->organization_id !== (int) $admin->organization_id
        ) {
            abort(422, 'Ce client est deja rattache a un autre partenaire.');
        }

        if ($clientAccount && (int) $clientAccount->organization_id !== (int) $admin->organization_id) {
            $clientAccount->forceFill([
                'organization_id' => $admin->organization_id,
            ])->save();
        }

        return $admin->organization_id;
    }
}
