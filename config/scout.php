<?php
// config/scout.php  ─ remplace le fichier par défaut

return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),  // 'meilisearch' | 'database' | 'null'
    'prefix' => env('SCOUT_PREFIX', ''),
    'queue'  => env('SCOUT_QUEUE', true),
    'chunk'  => ['searchable' => 500, 'unsearchable' => 500],
    'soft_delete' => false,

    'meilisearch' => [
        'host'    => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key'     => env('MEILISEARCH_KEY', null),
        'index-settings' => [
            'listings' => [
                'filterableAttributes' => [
                    'publication_status', 'listing_type', 'auction_status',
                    'vehicle_make', 'vehicle_model', 'vehicle_fuel_type',
                    'vehicle_gearbox', 'vehicle_body_type', 'vehicle_origin_country',
                    'vehicle_year', 'vehicle_mileage', 'vehicle_power_hp',
                    'vat_deductible', 'is_featured', 'display_price',
                    'ends_at_timestamp', 'published_at_timestamp',
                ],
                'sortableAttributes' => [
                    'display_price', 'ends_at_timestamp', 'published_at_timestamp',
                    'vehicle_mileage', 'vehicle_year',
                ],
                'searchableAttributes' => [
                    'title', 'vehicle_make', 'vehicle_model', 'vehicle_version',
                    'short_description', 'vehicle_color', 'vehicle_body_type',
                ],
                'faceting' => [
                    'maxValuesPerFacet' => 100,
                ],
                'pagination' => [
                    'maxTotalHits' => 10000,
                ],
            ],
        ],
    ],
];
