<?php

namespace App\Services\Imports\EcarsTrade;

use App\Models\ExternalListing;
use App\Models\ListingSimilarity;

class EcarsTradeSimilarityService
{
    /**
     * @return array<int, array{listing_id:int, score:int, breakdown:array<string,int>}>
     */
    public function computeTopSimilarities(ExternalListing $listing, int $limit = 6): array
    {
        $candidates = ExternalListing::query()
            ->where('id', '!=', $listing->id)
            ->whereNotNull('make')
            ->whereNotNull('model')
            ->where(function ($query) use ($listing): void {
                $query
                    ->where('make', $listing->make)
                    ->orWhere('model', $listing->model);
            })
            ->limit(200)
            ->get();

        $scored = [];
        foreach ($candidates as $candidate) {
            $breakdown = $this->scoreBreakdown($listing, $candidate);
            $score = array_sum($breakdown);
            if ($score <= 0) {
                continue;
            }

            $scored[] = [
                'listing_id' => (int) $candidate->id,
                'score' => $score,
                'breakdown' => $breakdown,
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $limit));
    }

    public function persistTopSimilarities(ExternalListing $listing, int $limit = 6): void
    {
        $top = $this->computeTopSimilarities($listing, $limit);

        ListingSimilarity::query()
            ->where('external_listing_id', $listing->id)
            ->delete();

        foreach ($top as $row) {
            ListingSimilarity::query()->create([
                'external_listing_id' => $listing->id,
                'similar_external_listing_id' => $row['listing_id'],
                'score' => $row['score'],
                'score_breakdown' => $row['breakdown'],
            ]);
        }
    }

    /**
     * +40 même marque/modèle
     * +20 année proche
     * +15 kilométrage proche
     * +10 même carburant
     * +10 même boîte
     * +5 même pays
     *
     * @return array<string, int>
     */
    private function scoreBreakdown(ExternalListing $base, ExternalListing $candidate): array
    {
        $brandModel = 0;
        if ($base->make !== null && $base->model !== null
            && $base->make === $candidate->make
            && $base->model === $candidate->model) {
            $brandModel = 40;
        }

        $yearScore = 0;
        if ($base->year !== null && $candidate->year !== null && abs($base->year - $candidate->year) <= 2) {
            $yearScore = 20;
        }

        $mileageScore = 0;
        if ($base->mileage !== null && $candidate->mileage !== null && abs($base->mileage - $candidate->mileage) <= 30000) {
            $mileageScore = 15;
        }

        $fuelScore = ($base->fuel !== null && $base->fuel === $candidate->fuel) ? 10 : 0;
        $transmissionScore = ($base->transmission !== null && $base->transmission === $candidate->transmission) ? 10 : 0;
        $countryScore = ($base->country !== null && $base->country !== '' && $base->country === $candidate->country) ? 5 : 0;

        return [
            'brand_model' => $brandModel,
            'year' => $yearScore,
            'mileage' => $mileageScore,
            'fuel' => $fuelScore,
            'transmission' => $transmissionScore,
            'country' => $countryScore,
        ];
    }
}
