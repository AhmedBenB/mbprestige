<?php

namespace App\Services;

use App\Jobs\RunCustomerSearchJob;
use App\Models\CustomerSearch;
use App\Models\Organization;
use App\Models\SearchResult;
use App\Models\SearchRun;
use App\Notifications\ClientSearchRequestConfirmationNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CustomerSearchDistributionService
{
    public function __construct(
        private readonly AdminSettingsService $settingsService,
        private readonly OrganizationEcarsTradeAccountService $organizationEcarsTradeAccountService,
    ) {
    }

    public function createRootSearch(array $attributes): CustomerSearch
    {
        return CustomerSearch::query()->create(array_merge($attributes, [
            'organization_id' => null,
            'parent_search_id' => null,
        ]));
    }

    public function updateRootSearch(CustomerSearch $search, array $attributes): CustomerSearch
    {
        $search->fill(array_merge($attributes, [
            'organization_id' => null,
            'parent_search_id' => null,
        ]));
        $search->save();

        return $search->fresh();
    }

    /**
     * @return Collection<int, Organization>
     */
    public function selectedOrganizations(): Collection
    {
        $ids = $this->selectedOrganizationIds();

        if ($ids === []) {
            return collect();
        }

        return Organization::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<int>
     */
    public function selectedOrganizationIds(): array
    {
        $routing = $this->settingsService->all()['routing'] ?? [];

        return collect($routing['selected_organization_ids'] ?? [])
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, CustomerSearch>
     */
    public function syncSelectedOrganizations(CustomerSearch $rootSearch): Collection
    {
        $rootSearch = $rootSearch->fresh();
        $organizations = $this->selectedOrganizations();
        $selectedOrganizationIds = $organizations
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($organizations->isEmpty()) {
            Log::warning('Centralized client search has no selected recipient organizations', [
                'search_id' => $rootSearch->id,
                'client_email' => $this->maskEmail($rootSearch->client_email),
                'selected_organization_ids' => $selectedOrganizationIds,
            ]);

            return collect();
        }

        $existingChildren = $rootSearch->distributedChildren()
            ->get()
            ->keyBy(fn (CustomerSearch $search) => (int) $search->organization_id);

        return $organizations->map(function (Organization $organization) use ($existingChildren, $rootSearch): CustomerSearch {
            $attributes = $this->distributedSearchAttributes($rootSearch, $organization->id);
            $childSearch = $existingChildren->get((int) $organization->id);

            if ($childSearch) {
                $childSearch->fill($attributes);
                $childSearch->save();

                return $childSearch->fresh();
            }

            return CustomerSearch::query()->create($attributes);
        })->values();
    }

    /**
     * @return array{queued:int, skipped:int, children:Collection<int, CustomerSearch>}
     */
    public function dispatchSelectedOrganizations(CustomerSearch $rootSearch, bool $afterResponse = false): array
    {
        $children = $this->syncSelectedOrganizations($rootSearch);
        $queued = 0;
        $skipped = 0;
        $selectedOrganizationIds = $children
            ->pluck('organization_id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        foreach ($children as $childSearch) {
            if ($childSearch->status !== CustomerSearch::STATUS_ACTIVE) {
                $skipped++;
                continue;
            }

            if ($this->organizationEcarsTradeAccountService->readinessMessageForSearch($childSearch) !== null) {
                $skipped++;
                continue;
            }

            if ($afterResponse) {
                RunCustomerSearchJob::dispatchAfterResponse($childSearch);
            } else {
                RunCustomerSearchJob::dispatch($childSearch);
            }

            $queued++;
        }

        Log::info('Centralized client search distribution completed', [
            'root_search_id' => $rootSearch->id,
            'client_email' => $this->maskEmail($rootSearch->client_email),
            'selected_organization_ids' => $selectedOrganizationIds,
            'child_search_ids' => $children->pluck('id')->map(static fn ($id) => (int) $id)->values()->all(),
            'queued' => $queued,
            'skipped' => $skipped,
            'after_response' => $afterResponse,
        ]);

        return [
            'queued' => $queued,
            'skipped' => $skipped,
            'children' => $children,
        ];
    }

    public function sendConfirmation(CustomerSearch $search): void
    {
        $email = trim((string) $search->client_email);

        if ($email === '') {
            Log::warning('Client search confirmation email skipped because client email is empty', [
                'search_id' => $search->id,
            ]);
            return;
        }

        try {
            Notification::route('mail', $email)
                ->notify(new ClientSearchRequestConfirmationNotification($search));

            Log::info('Client search confirmation email sent', [
                'search_id' => $search->id,
                'client_email' => $this->maskEmail($email),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Client search confirmation email failed', [
                'search_id' => $search->id,
                'client_email' => $this->maskEmail($email),
                'message' => $exception->getMessage(),
            ]);

            report($exception);
        }
    }

    /**
     * @return Collection<int, SearchResult>
     */
    public function visibleResults(CustomerSearch $search, int $limit = 20): Collection
    {
        return $this->visibleResultsQuery($search)
            ->latest('search_results.created_at')
            ->limit($limit)
            ->get();
    }

    public function visibleResultsCount(CustomerSearch $search): int
    {
        return $this->visibleResultsQuery($search)->count();
    }

    /**
     * @return Collection<int, SearchRun>
     */
    public function recentRuns(CustomerSearch $search, int $limit = 10): Collection
    {
        return $this->runsQuery($search)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function latestRun(CustomerSearch $search): ?SearchRun
    {
        return $this->runsQuery($search)
            ->latest('created_at')
            ->first();
    }

    /**
     * @return Collection<int, SearchResult>
     */
    public function allResults(CustomerSearch $search): Collection
    {
        return $this->resultsQuery($search)
            ->latest('search_results.created_at')
            ->get();
    }

    /**
     * @return list<int>
     */
    public function removeOrganizationFromRouting(int $organizationId): array
    {
        $remaining = array_values(array_filter(
            $this->selectedOrganizationIds(),
            static fn ($id) => (int) $id !== (int) $organizationId
        ));

        $saved = $this->settingsService->save([
            'routing' => [
                'selected_organization_ids' => $remaining,
            ],
        ]);

        return collect($saved['routing']['selected_organization_ids'] ?? [])
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Builder<SearchResult>
     */
    public function resultsQuery(CustomerSearch $search): Builder
    {
        if (! $search->isDistributedRoot()) {
            return $search->results();
        }

        return SearchResult::query()
            ->whereHas('search', fn (Builder $builder) => $builder->where('parent_search_id', $search->id));
    }

    /**
     * @return Builder<SearchRun>
     */
    public function runsQuery(CustomerSearch $search): Builder
    {
        if (! $search->isDistributedRoot()) {
            return $search->runs();
        }

        return SearchRun::query()
            ->whereHas('search', fn (Builder $builder) => $builder->where('parent_search_id', $search->id));
    }

    /**
     * @return Builder<SearchResult>
     */
    private function visibleResultsQuery(CustomerSearch $search): Builder
    {
        return $this->resultsQuery($search)
            ->whereIn('match_status', [
                SearchResult::STATUS_APPROVED,
                SearchResult::STATUS_SHARED,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function distributedSearchAttributes(CustomerSearch $rootSearch, int $organizationId): array
    {
        return [
            'parent_search_id' => $rootSearch->id,
            'created_by' => $rootSearch->created_by,
            'user_id' => $rootSearch->user_id,
            'organization_id' => $organizationId,
            'client_name' => $rootSearch->client_name,
            'client_first_name' => $rootSearch->client_first_name,
            'client_last_name' => $rootSearch->client_last_name,
            'client_email' => $rootSearch->client_email,
            'client_phone' => $rootSearch->client_phone,
            'client_comment' => $rootSearch->client_comment,
            'consent_email' => (bool) $rootSearch->consent_email,
            'consent_sms' => (bool) $rootSearch->consent_sms,
            'make' => $rootSearch->make,
            'model' => $rootSearch->model,
            'budget_max' => $rootSearch->budget_max,
            'year_min' => $rootSearch->year_min,
            'fuel' => $rootSearch->fuel,
            'transmission' => $rootSearch->transmission,
            'mileage_max' => $rootSearch->mileage_max,
            'mileage_tolerance' => $rootSearch->mileage_tolerance,
            'color' => $rootSearch->color,
            'source_zone' => $rootSearch->source_zone,
            'status' => $rootSearch->status,
            'manage_token' => null,
            'unsubscribe_token' => null,
        ];
    }

    private function maskEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '' || !str_contains($email, '@')) {
            return $email !== '' ? '***' : null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = trim($local);
        $domain = trim($domain);

        if ($local === '' || $domain === '') {
            return '***';
        }

        $prefix = mb_substr($local, 0, 2);

        return $prefix . '***@' . $domain;
    }
}
