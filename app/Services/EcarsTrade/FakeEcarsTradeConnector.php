<?php

namespace App\Services\EcarsTrade;

use App\DataTransferObjects\EcarsTradeListingData;
use App\DataTransferObjects\SearchCriteriaData;
use App\Services\EcarsTrade\Contracts\EcarsTradeConnectorInterface;
use App\Support\VehicleCatalog;

class FakeEcarsTradeConnector implements EcarsTradeConnectorInterface
{
    public function authenticate(): void
    {
        // Faux connecteur pour accelerer les tests locaux.
    }

    public function search(SearchCriteriaData $criteria): array
    {
        $models = VehicleCatalog::modelsForSelection($criteria->make, $criteria->model);
        $primaryModel = $models[0] ?? $criteria->model;
        $secondaryModel = $models[1] ?? $primaryModel;

        $base = [
            [
                'source_ref' => 'ECT-1001',
                'url' => 'https://ecarstrade.com/listing/ECT-1001',
                'title' => trim("{$criteria->make} {$primaryModel} 1"),
                'make' => $criteria->make,
                'model' => $primaryModel,
                'price' => max(1000, $criteria->budgetMax - 750),
                'year' => $criteria->yearMin + 1,
                'fuel' => $criteria->fuel ?? 'diesel',
                'gearbox' => $criteria->transmission ?? 'automatic',
                'mileage' => $criteria->mileageMax ? max(0, $criteria->mileageMax - 5000) : 78000,
                'color' => $criteria->color ?? 'white',
            ],
            [
                'source_ref' => 'ECT-1002',
                'url' => 'https://ecarstrade.com/listing/ECT-1002',
                'title' => trim("{$criteria->make} {$secondaryModel} 2"),
                'make' => $criteria->make,
                'model' => $secondaryModel,
                'price' => $criteria->budgetMax + 300,
                'year' => $criteria->yearMin,
                'fuel' => $criteria->fuel ?? 'diesel',
                'gearbox' => $criteria->transmission ?? 'manual',
                'mileage' => $criteria->mileageMax ? $criteria->mileageMax + 2000 : 91000,
                'color' => $criteria->color ?? 'gray',
            ],
            [
                'source_ref' => 'ECT-1003',
                'url' => 'https://ecarstrade.com/listing/ECT-1003',
                'title' => 'Vehicule hors cible',
                'make' => $criteria->make,
                'model' => 'Autre modele',
                'price' => $criteria->budgetMax - 1000,
                'year' => $criteria->yearMin + 2,
                'fuel' => 'essence',
                'gearbox' => 'manual',
                'mileage' => 60000,
                'color' => 'black',
            ],
        ];

        return array_map(
            static fn (array $payload) => EcarsTradeListingData::fromArray($payload),
            $base
        );
    }

    public function fetchListingDetails(EcarsTradeListingData $listing): array
    {
        return [
            'images' => [
                'https://images.example.test/ecarstrade/' . rawurlencode((string) ($listing->sourceRef ?? 'listing')) . '/1.jpg',
                'https://images.example.test/ecarstrade/' . rawurlencode((string) ($listing->sourceRef ?? 'listing')) . '/2.jpg',
            ],
            'documents' => [
                [
                    'type' => 'report',
                    'title' => 'Expert report',
                    'url' => 'https://docs.example.test/ecarstrade/' . rawurlencode((string) ($listing->sourceRef ?? 'listing')) . '/report.pdf',
                ],
            ],
        ];
    }
}
