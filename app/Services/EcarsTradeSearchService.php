<?php

namespace App\Services;

use App\DataTransferObjects\SearchCriteriaData;
use App\Models\CustomerSearch;
use App\Services\EcarsTrade\Contracts\EcarsTradeConnectorInterface;

class EcarsTradeSearchService
{
    public function __construct(
        private readonly EcarsTradeConnectorInterface $connector,
        private readonly OrganizationEcarsTradeAccountService $organizationEcarsTradeAccountService,
    ) {}

    public function search(SearchCriteriaData $criteria): array
    {
        $this->connector->authenticate();

        return $this->connector->search($criteria);
    }

    public function execute(CustomerSearch $search): array
    {
        $criteria = SearchCriteriaData::fromModel($search);

        return $this->organizationEcarsTradeAccountService->runWithSearchCredentials(
            $search,
            fn () => $this->search($criteria),
        );
    }
}
