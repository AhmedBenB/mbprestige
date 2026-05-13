<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicRequest;
use App\Http\Requests\UpdatePublicRequest;
use App\Models\CustomerSearch;
use App\Models\Organization;
use App\Models\SearchResult;
use App\Models\SearchRun;
use App\Services\AuditLogService;
use App\Services\ClientAccountService;
use App\Services\CustomerSearchDistributionService;
use App\Services\SearchResultFormatter;
use App\Support\VehicleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicRequestController extends Controller
{
    public function catalog(): JsonResponse
    {
        return response()->json([
            'data' => [
                'makes' => VehicleCatalog::makes(),
                'models_by_make' => VehicleCatalog::modelsByMake(),
            ],
        ]);
    }

    public function partner(string $slug): JsonResponse
    {
        $organization = $this->resolvePartnerSlug($slug);

        return response()->json([
            'data' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'location' => $organization->location,
                'description' => $organization->description,
                'partner_code' => $organization->partner_code,
                'is_active' => (bool) $organization->is_active,
            ],
        ]);
    }

    public function garages(): JsonResponse
    {
        return response()->json([
            'data' => Organization::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'location', 'description'])
                ->map(fn (Organization $organization) => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'location' => $organization->location,
                    'description' => $organization->description,
                ])
                ->values(),
        ]);
    }

    public function store(
        StorePublicRequest $request,
        AuditLogService $auditLogService,
        CustomerSearchDistributionService $distributionService,
    ): JsonResponse {
        $criteria = $request->validated('criteria');
        $organization = $this->resolveOptionalPartnerSlug($request->validated('partner_slug'));
        [$firstName, $lastName, $fullName] = $this->normalizePublicNamePayload($request->validated());

        $clientAccount = app(ClientAccountService::class)->resolveOrCreate(
            $request->validated('email'),
            $firstName,
            $lastName,
            $request->validated('phone'),
            null,
        );

        $search = $distributionService->createRootSearch([
            'user_id' => $clientAccount?->id,
            'client_name' => $fullName,
            'client_first_name' => $firstName,
            'client_last_name' => $lastName,
            'client_email' => $request->validated('email'),
            'client_phone' => $request->validated('phone'),
            'client_comment' => $request->validated('comment'),
            'consent_email' => (bool) $request->validated('consent_email'),
            'consent_sms' => (bool) $request->validated('consent_sms'),
            'manage_token' => Str::random(40),
            'unsubscribe_token' => Str::random(40),
            'make' => $criteria['make'],
            'model' => $criteria['model'],
            'budget_max' => $criteria['budget_max'],
            'year_min' => $criteria['year_min'],
            'fuel' => $criteria['fuel'] ?? null,
            'transmission' => $criteria['transmission'] ?? null,
            'mileage_max' => $criteria['mileage_max'] ?? null,
            'color' => $criteria['color'] ?? null,
            'mileage_tolerance' => 10000,
            'source_zone' => 'all_cars',
            'status' => CustomerSearch::STATUS_ACTIVE,
        ]);

        $distribution = $distributionService->dispatchSelectedOrganizations($search, true);
        $distributionService->sendConfirmation($search);

        $auditLogService->record(
            'public.search.created',
            $clientAccount,
            [
                'organization_id' => null,
                'partner_slug' => $organization?->slug,
                'distributed_queued' => $distribution['queued'],
                'distributed_skipped' => $distribution['skipped'],
            ],
            $search,
            request: $request,
        );

        return response()->json([
            'message' => 'Demande enregistree.',
            'data' => $this->publicResource($search),
        ], 201);
    }

    public function show(string $token, CustomerSearchDistributionService $distributionService): JsonResponse
    {
        $search = $this->resolveManageToken($token);
        $search = $this->loadPublicFacingSearch($search, $distributionService);

        $latestRun = $search->runs->first();
        $progress = $this->progressLabel($search, $latestRun);
        $resultFormatter = app(SearchResultFormatter::class);

        return response()->json([
            'data' => [
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
                'search' => [
                    'id' => $search->id,
                    'status' => $search->status,
                    'created_at' => optional($search->created_at)->toIso8601String(),
                    'last_run_at' => optional($search->last_run_at)->toIso8601String(),
                    'progress_label' => $progress,
                    'criteria' => $this->criteriaResource($search),
                ],
                'account' => [
                    'has_account' => $search->user_id !== null,
                    'claim_available' => $search->user_id !== null,
                    'email' => $search->client_email,
                ],
                'results' => $search->results->map(function (SearchResult $result) use ($resultFormatter) {
                    $formatted = $resultFormatter->format($result);

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
                })->values(),
                'recent_runs' => $search->runs->map(fn (SearchRun $run) => [
                    'id' => $run->id,
                    'status' => $run->status,
                    'result_count' => $run->result_count,
                    'public_message' => $this->publicRunMessage($run),
                    'started_at' => optional($run->started_at)->toIso8601String(),
                    'finished_at' => optional($run->finished_at)->toIso8601String(),
                    'created_at' => optional($run->created_at)->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function update(
        UpdatePublicRequest $request,
        string $token,
        CustomerSearchDistributionService $distributionService,
    ): JsonResponse {
        $search = $this->resolveManageToken($token);
        $criteria = $request->validated('criteria');
        [$firstName, $lastName, $fullName] = $this->normalizePublicNamePayload($request->validated());

        $clientAccount = app(ClientAccountService::class)->resolveOrCreate(
            $request->validated('email'),
            $firstName,
            $lastName,
            $request->validated('phone'),
            null,
        );

        $search = $distributionService->updateRootSearch($search, [
            'user_id' => $clientAccount?->id,
            'client_name' => $fullName,
            'client_first_name' => $firstName,
            'client_last_name' => $lastName,
            'client_email' => $request->validated('email'),
            'client_phone' => $request->validated('phone'),
            'client_comment' => $request->validated('comment'),
            'consent_email' => (bool) $request->validated('consent_email'),
            'consent_sms' => (bool) $request->validated('consent_sms'),
            'make' => $criteria['make'],
            'model' => $criteria['model'],
            'budget_max' => $criteria['budget_max'],
            'year_min' => $criteria['year_min'],
            'fuel' => $criteria['fuel'] ?? null,
            'transmission' => $criteria['transmission'] ?? null,
            'mileage_max' => $criteria['mileage_max'] ?? null,
            'color' => $criteria['color'] ?? null,
        ]);

        $distributionService->dispatchSelectedOrganizations($search, true);
        $distributionService->sendConfirmation($search);

        return response()->json([
            'message' => 'Demande mise a jour.',
            'data' => [
                'search_id' => $search->id,
                'status' => $search->status,
                'updated_at' => optional($search->updated_at)->toIso8601String(),
            ],
        ]);
    }

    public function unsubscribeEmail(Request $request): JsonResponse
    {
        $search = $this->resolveUnsubscribeToken((string) $request->input('token', $request->query('token', '')));
        $search->update(['consent_email' => false]);

        return response()->json([
            'message' => 'Desinscription email enregistree.',
            'data' => [
                'consent_email' => false,
            ],
        ]);
    }

    public function unsubscribeSms(Request $request): JsonResponse
    {
        $search = $this->resolveUnsubscribeToken((string) $request->input('token', $request->query('token', '')));
        $search->update(['consent_sms' => false]);

        return response()->json([
            'message' => 'Desinscription SMS enregistree.',
            'data' => [
                'consent_sms' => false,
            ],
        ]);
    }

    private function publicResource(CustomerSearch $search): array
    {
        return [
            'search_id' => $search->id,
            'status' => $search->status,
            'manage_token' => $search->manage_token,
            'unsubscribe_token' => $search->unsubscribe_token,
            'account' => [
                'has_account' => $search->user_id !== null,
                'claim_available' => $search->user_id !== null,
                'email' => $search->client_email,
            ],
            'partner' => null,
            'manage_url' => url('/api/public/requests/' . $search->manage_token),
            'unsubscribe_email_url' => url('/api/public/unsubscribe/email?token=' . $search->unsubscribe_token),
            'unsubscribe_sms_url' => url('/api/public/unsubscribe/sms?token=' . $search->unsubscribe_token),
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

    private function normalizePublicNamePayload(array $payload): array
    {
        $firstName = trim((string) ($payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['last_name'] ?? ''));
        $fullName = trim((string) ($payload['full_name'] ?? ''));

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

    private function resolveManageToken(string $token): CustomerSearch
    {
        $query = CustomerSearch::query()
            ->whereNull('parent_search_id')
            ->where('manage_token', $token);

        if (ctype_digit($token)) {
            $query->orWhere(function ($builder) use ($token): void {
                $builder
                    ->whereNull('parent_search_id')
                    ->whereKey((int) $token);
            });
        }

        return $query->firstOrFail();
    }

    private function resolveUnsubscribeToken(string $token): CustomerSearch
    {
        $query = CustomerSearch::query()
            ->whereNull('parent_search_id')
            ->where('unsubscribe_token', $token);

        if (ctype_digit($token)) {
            $query->orWhere(function ($builder) use ($token): void {
                $builder
                    ->whereNull('parent_search_id')
                    ->whereKey((int) $token);
            });
        }

        return $query->firstOrFail();
    }

    private function resolveOptionalPartnerSlug(?string $slug): ?Organization
    {
        $slug = trim((string) $slug);

        return $slug !== '' ? $this->resolvePartnerSlug($slug) : null;
    }

    private function resolvePartnerSlug(string $slug): Organization
    {
        return Organization::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function loadPublicFacingSearch(
        CustomerSearch $search,
        CustomerSearchDistributionService $distributionService,
    ): CustomerSearch {
        $runs = $distributionService->recentRuns($search, 10);
        $results = $distributionService->visibleResults($search, 20);

        $search->setRelation('runs', $runs);
        $search->setRelation('results', $results);

        return $search;
    }
}
