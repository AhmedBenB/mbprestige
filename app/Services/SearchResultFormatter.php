<?php

namespace App\Services;

use App\Models\SearchResult;

class SearchResultFormatter
{
    public function format(SearchResult $result): array
    {
        $details = [];

        if ($result->price !== null) {
            $details[] = number_format($result->price, 0, ',', ' ') . ' EUR';
        }

        if ($result->year !== null) {
            $details[] = (string) $result->year;
        }

        if ($result->fuel) {
            $details[] = $result->fuel;
        }

        if ($result->gearbox) {
            $details[] = $result->gearbox;
        }

        if ($result->mileage !== null) {
            $details[] = number_format($result->mileage, 0, ',', ' ') . ' km';
        }

        if ($result->color) {
            $details[] = $result->color;
        }

        return [
            'id' => $result->id,
            'url' => $result->listing_url,
            'title' => $result->title ?: trim(($result->make ?? '') . ' ' . ($result->model ?? '')),
            'details' => implode(' | ', $details),
            'price' => $result->price,
            'year' => $result->year,
            'fuel' => $result->fuel,
            'gearbox' => $result->gearbox,
            'mileage' => $result->mileage,
            'color' => $result->color,
            'decision' => $result->match_status,
            'score' => $result->match_score,
        ];
    }
}
