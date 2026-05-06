<?php

namespace App\Services\EcarsTrade\Contracts;

use App\DataTransferObjects\EcarsTradeListingData;
use App\DataTransferObjects\SearchCriteriaData;

interface EcarsTradeConnectorInterface
{
    public function authenticate(): void;

    /**
     * @return EcarsTradeListingData[]
     */
    public function search(SearchCriteriaData $criteria): array;
}
