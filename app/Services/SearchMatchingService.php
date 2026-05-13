<?php

namespace App\Services;

use App\DataTransferObjects\EcarsTradeListingData;
use App\DataTransferObjects\SearchCriteriaData;
use App\Support\VehicleCatalog;

class SearchMatchingService
{
    private const PREFERRED_MIN_BUDGET_GAP = 2000;
    private const PREFERRED_MAX_BUDGET_GAP = 3000;

    public function matches(EcarsTradeListingData $listing, SearchCriteriaData $criteria): bool
    {
        if (!$this->equals($listing->make, $criteria->make)) {
            return false;
        }

        if ($criteria->model) {
            $acceptableModels = VehicleCatalog::modelsForSelection($criteria->make, $criteria->model);

            if ($acceptableModels === []) {
                $acceptableModels = [$criteria->model];
            }

            $matchesModel = false;
            foreach ($acceptableModels as $acceptableModel) {
                if ($this->equals($listing->model, $acceptableModel)) {
                    $matchesModel = true;
                    break;
                }
            }

            if (!$matchesModel) {
                return false;
            }
        }

        if (!$this->matchesBudgetRule($listing, $criteria)) {
            return false;
        }

        if ($listing->year === null || $listing->year < $criteria->yearMin) {
            return false;
        }

        if ($criteria->fuel && !$this->equals($listing->fuel, $criteria->fuel)) {
            return false;
        }

        if ($criteria->transmission && !$this->equals($listing->gearbox, $criteria->transmission)) {
            return false;
        }

        if ($criteria->color && !$this->equals($listing->color, $criteria->color)) {
            return false;
        }

        if ($criteria->mileageMax !== null) {
            if ($listing->mileage === null) {
                return false;
            }

            if ($listing->mileage > ($criteria->mileageMax + $criteria->mileageTolerance)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  EcarsTradeListingData[]  $listings
     * @return EcarsTradeListingData[]
     */
    public function filter(array $listings, SearchCriteriaData $criteria): array
    {
        return array_values(array_filter(
            $listings,
            fn (EcarsTradeListingData $listing) => $this->matches($listing, $criteria)
        ));
    }

    public function score(EcarsTradeListingData $listing, SearchCriteriaData $criteria): int
    {
        if (!$this->matches($listing, $criteria)) {
            return 0;
        }

        $score = 60;

        $budgetGap = $this->priceGap($listing, $criteria);
        if ($budgetGap !== null) {
            if ($this->isWithinPreferredBudgetWindow($budgetGap)) {
                $score += 15;
            } else {
                $distanceToWindow = max(0, $budgetGap - self::PREFERRED_MAX_BUDGET_GAP);
                $windowRatio = 1 - min(1, $distanceToWindow / 10000);
                $score += (int) round($windowRatio * 8);
            }
        }

        if ($listing->year !== null) {
            $yearGap = max(0, $listing->year - $criteria->yearMin);
            $score += min(10, $yearGap * 2);
        }

        if ($criteria->fuel === null || $this->equals($listing->fuel, $criteria->fuel)) {
            $score += 5;
        }

        if ($criteria->transmission === null || $this->equals($listing->gearbox, $criteria->transmission)) {
            $score += 5;
        }

        if ($criteria->color === null || $this->equals($listing->color, $criteria->color)) {
            $score += 5;
        }

        if ($criteria->mileageMax === null) {
            $score += 5;
        } elseif ($listing->mileage !== null) {
            $delta = max(0, $listing->mileage - $criteria->mileageMax);
            $ratio = 1 - min(1, $delta / max(1, $criteria->mileageTolerance));
            $score += (int) round($ratio * 5);
        }

        return max(0, min(100, $score));
    }

    private function equals(?string $left, ?string $right): bool
    {
        return strtolower(trim((string) $left)) === strtolower(trim((string) $right));
    }

    private function matchesBudgetRule(EcarsTradeListingData $listing, SearchCriteriaData $criteria): bool
    {
        $budgetGap = $this->priceGap($listing, $criteria);

        return $budgetGap !== null
            && $budgetGap >= 0;
    }

    private function priceGap(EcarsTradeListingData $listing, SearchCriteriaData $criteria): ?float
    {
        if ($listing->price === null || $criteria->budgetMax <= 0) {
            return null;
        }

        return (float) $criteria->budgetMax - (float) $listing->price;
    }

    private function isWithinPreferredBudgetWindow(float $budgetGap): bool
    {
        return $budgetGap >= self::PREFERRED_MIN_BUDGET_GAP
            && $budgetGap <= self::PREFERRED_MAX_BUDGET_GAP;
    }
}
