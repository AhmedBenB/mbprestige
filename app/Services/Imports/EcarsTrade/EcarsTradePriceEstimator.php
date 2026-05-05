<?php

namespace App\Services\Imports\EcarsTrade;

use App\Models\ExternalListing;
use App\Models\ListingPriceEstimate;

class EcarsTradePriceEstimator
{
    /**
     * @return ListingPriceEstimate|null
     */
    public function estimate(ExternalListing $listing): ?ListingPriceEstimate
    {
        if ($listing->price_visible && $listing->price_amount !== null) {
            return null;
        }

        $similars = ExternalListing::query()
            ->where('id', '!=', $listing->id)
            ->where('make', $listing->make)
            ->where('model', $listing->model)
            ->whereNotNull('price_amount')
            ->where('price_visible', true)
            ->get()
            ->filter(function (ExternalListing $candidate) use ($listing): bool {
                if ($listing->year !== null && $candidate->year !== null && abs($listing->year - $candidate->year) > 2) {
                    return false;
                }

                if ($listing->mileage !== null && $candidate->mileage !== null && abs($listing->mileage - $candidate->mileage) > 30000) {
                    return false;
                }

                if ($listing->fuel !== null && $candidate->fuel !== null && $listing->fuel !== $candidate->fuel) {
                    return false;
                }

                if ($listing->transmission !== null && $candidate->transmission !== null && $listing->transmission !== $candidate->transmission) {
                    return false;
                }

                return true;
            })
            ->values();

        if ($similars->isEmpty()) {
            return null;
        }

        $baseAverage = (float) $similars->avg('price_amount');
        $adjusted = $this->applyAdjustments($baseAverage, $listing, $similars->all());

        $marginMin = (float) config('ecarstrade.import.margin_min', 2000);
        $marginMax = (float) config('ecarstrade.import.margin_max', 3000);

        $estimatedMin = max(0, $adjusted + $marginMin);
        $estimatedMax = max($estimatedMin, $adjusted + $marginMax);

        $sampleSize = $similars->count();
        $confidence = $this->confidenceScore($sampleSize);
        $label = $this->confidenceLabel($confidence);

        return ListingPriceEstimate::query()->create([
            'external_listing_id' => $listing->id,
            'estimated_price_min' => round($estimatedMin, 2),
            'estimated_price_max' => round($estimatedMax, 2),
            'estimated_price_confidence' => $confidence,
            'confidence_label' => $label,
            'estimated_price_reason' => sprintf(
                'Estimation basee sur %d annonces similaires (marque/modele/annee/km proches), avec marge MBPRESTIGE.',
                $sampleSize
            ),
            'sample_size' => $sampleSize,
            'meta' => [
                'base_average' => round($baseAverage, 2),
                'adjusted_base' => round($adjusted, 2),
                'margin_min' => $marginMin,
                'margin_max' => $marginMax,
            ],
        ]);
    }

    /**
     * @param  array<int, ExternalListing>  $similars
     */
    private function applyAdjustments(float $price, ExternalListing $target, array $similars): float
    {
        $adjusted = $price;

        if ($target->mileage !== null) {
            $avgMileage = collect($similars)->avg('mileage');
            if (is_numeric($avgMileage)) {
                $kmDiff = $target->mileage - (float) $avgMileage;
                $adjusted -= ($kmDiff / 10000) * 120;
            }
        }

        if ($target->year !== null) {
            $avgYear = collect($similars)->avg('year');
            if (is_numeric($avgYear)) {
                $yearDiff = $target->year - (float) $avgYear;
                $adjusted += $yearDiff * 250;
            }
        }

        return max(0, $adjusted);
    }

    private function confidenceScore(int $sampleSize): float
    {
        if ($sampleSize >= 10) {
            return 0.85;
        }

        if ($sampleSize >= 6) {
            return 0.70;
        }

        if ($sampleSize >= 3) {
            return 0.55;
        }

        return 0.35;
    }

    private function confidenceLabel(float $score): string
    {
        return match (true) {
            $score >= 0.8 => 'high',
            $score >= 0.6 => 'medium',
            default => 'low',
        };
    }
}
