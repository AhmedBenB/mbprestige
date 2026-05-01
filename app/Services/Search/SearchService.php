<?php

namespace App\Services\Search;

use App\Models\Listing;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SearchService
{
    /**
     * Recherche principale : utilise Meilisearch si disponible, MySQL sinon.
     */
    public function search(array $filters = [], int $perPage = 24, int $page = 1): array
    {
        try {
            return $this->meilisearchQuery($filters, $perPage, $page);
        } catch (\Throwable $e) {
            // Fallback sur MySQL si Meilisearch indisponible
            return $this->mysqlQuery($filters, $perPage, $page);
        }
    }

    /**
     * Recherche via Meilisearch (Scout).
     */
    private function meilisearchQuery(array $filters, int $perPage, int $page): array
    {
        $query = $filters['q'] ?? '';

        $scoutQuery = Listing::search($query, function ($meilisearch, string $query, array $options) use ($filters) {

            // Filtres Meilisearch
            $filterParts = ["publication_status = 'published'"];

            if (! empty($filters['make'])) {
                $filterParts[] = "vehicle_make = '" . addslashes($filters['make']) . "'";
            }
            if (! empty($filters['model'])) {
                $filterParts[] = "vehicle_model = '" . addslashes($filters['model']) . "'";
            }
            if (! empty($filters['fuel'])) {
                $filterParts[] = "vehicle_fuel_type = '" . addslashes($filters['fuel']) . "'";
            }
            if (! empty($filters['gearbox'])) {
                $filterParts[] = "vehicle_gearbox = '" . addslashes($filters['gearbox']) . "'";
            }
            if (! empty($filters['country'])) {
                $filterParts[] = "vehicle_origin_country = '" . addslashes($filters['country']) . "'";
            }
            if (! empty($filters['listing_type'])) {
                $filterParts[] = "listing_type = '" . addslashes($filters['listing_type']) . "'";
            }
            if (! empty($filters['year_min'])) {
                $filterParts[] = "vehicle_year >= " . (int) $filters['year_min'];
            }
            if (! empty($filters['year_max'])) {
                $filterParts[] = "vehicle_year <= " . (int) $filters['year_max'];
            }
            if (! empty($filters['km_max'])) {
                $filterParts[] = "vehicle_mileage <= " . (int) $filters['km_max'];
            }
            if (! empty($filters['price_min'])) {
                $filterParts[] = "display_price >= " . (float) $filters['price_min'];
            }
            if (! empty($filters['price_max'])) {
                $filterParts[] = "display_price <= " . (float) $filters['price_max'];
            }
            if (! empty($filters['vat'])) {
                $filterParts[] = "vat_deductible = true";
            }
            if (! empty($filters['body_type'])) {
                $filterParts[] = "vehicle_body_type = '" . addslashes($filters['body_type']) . "'";
            }

            $options['filter'] = implode(' AND ', $filterParts);

            // Tri
            $options['sort'] = match ($filters['sort'] ?? 'published_at') {
                'price_asc'  => ['display_price:asc'],
                'price_desc' => ['display_price:desc'],
                'ends_at'    => ['ends_at_timestamp:asc'],
                default      => ['published_at_timestamp:desc'],
            };

            // Facettes
            $options['facets'] = [
                'vehicle_make', 'vehicle_fuel_type', 'vehicle_gearbox',
                'vehicle_origin_country', 'vehicle_body_type', 'listing_type', 'vat_deductible',
            ];

            $options['hitsPerPage'] = 24;
            $options['page']        = $page ?? 1;

            return $meilisearch->search($query, $options);
        });

        $results = $scoutQuery->paginate($perPage, 'page', $page);

        return [
            'listings'     => $results,
            'total'        => $results->total(),
            'facets'       => [],   // Récupérés via rawSearch si besoin
            'engine'       => 'meilisearch',
        ];
    }

    /**
     * Fallback MySQL avec jointure vehicle.
     */
    private function mysqlQuery(array $filters, int $perPage, int $page): array
    {
        $query = Listing::published()
            ->with(['vehicle', 'coverImage', 'auction'])
            ->join('vehicles', 'vehicles.id', '=', 'listings.vehicle_id')
            ->select('listings.*');

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(fn ($s) =>
                $s->where('listings.title', 'like', "%{$q}%")
                  ->orWhere('vehicles.make', 'like', "%{$q}%")
                  ->orWhere('vehicles.model', 'like', "%{$q}%")
            );
        }
        if (! empty($filters['make']))        $query->where('vehicles.make', $filters['make']);
        if (! empty($filters['model']))       $query->where('vehicles.model', $filters['model']);
        if (! empty($filters['fuel']))        $query->where('vehicles.fuel_type', $filters['fuel']);
        if (! empty($filters['gearbox']))     $query->where('vehicles.gearbox', $filters['gearbox']);
        if (! empty($filters['country']))     $query->where('vehicles.origin_country', $filters['country']);
        if (! empty($filters['body_type']))   $query->where('vehicles.body_type', $filters['body_type']);
        if (! empty($filters['listing_type']))$query->where('listings.listing_type', $filters['listing_type']);
        if (! empty($filters['year_min']))    $query->whereYear('vehicles.first_registration_date', '>=', $filters['year_min']);
        if (! empty($filters['year_max']))    $query->whereYear('vehicles.first_registration_date', '<=', $filters['year_max']);
        if (! empty($filters['km_max']))      $query->where('vehicles.mileage', '<=', $filters['km_max']);
        if (! empty($filters['vat']))         $query->where('listings.vat_deductible', true);
        if (! empty($filters['price_min']))   $query->where(fn ($s) =>
            $s->where('listings.buy_now_price', '>=', $filters['price_min'])
              ->orWhere('listings.current_bid', '>=', $filters['price_min']));
        if (! empty($filters['price_max']))   $query->where(fn ($s) =>
            $s->where('listings.buy_now_price', '<=', $filters['price_max'])
              ->orWhere('listings.current_bid', '<=', $filters['price_max']));

        $sortCol = match ($filters['sort'] ?? '') {
            'price_asc', 'price_desc' => 'listings.buy_now_price',
            'ends_at'                 => 'listings.ends_at',
            default                   => 'listings.published_at',
        };
        $sortDir = in_array($filters['sort'] ?? '', ['price_asc']) ? 'asc' : 'desc';
        $query->orderBy($sortCol, $sortDir);

        $results = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'listings' => $results,
            'total'    => $results->total(),
            'facets'   => $this->buildFacets($filters),
            'engine'   => 'mysql',
        ];
    }

    /**
     * Facettes calculées côté MySQL pour le fallback.
     */
    public function buildFacets(array $activeFilters = []): array
    {
        return Cache::remember('search_facets_' . md5(json_encode($activeFilters)), 60, function () use ($activeFilters) {
            return [
                'makes'      => \App\Models\Vehicle::join('listings', 'listings.vehicle_id', '=', 'vehicles.id')
                    ->where('listings.publication_status', 'published')
                    ->distinct()->orderBy('make')->pluck('make')->filter()->values(),
                'fuels'      => \App\Models\Vehicle::join('listings', 'listings.vehicle_id', '=', 'vehicles.id')
                    ->where('listings.publication_status', 'published')
                    ->distinct()->orderBy('fuel_type')->pluck('fuel_type')->filter()->values(),
                'gearboxes'  => \App\Models\Vehicle::join('listings', 'listings.vehicle_id', '=', 'vehicles.id')
                    ->where('listings.publication_status', 'published')
                    ->distinct()->orderBy('gearbox')->pluck('gearbox')->filter()->values(),
                'countries'  => \App\Models\Vehicle::join('listings', 'listings.vehicle_id', '=', 'vehicles.id')
                    ->where('listings.publication_status', 'published')
                    ->distinct()->orderBy('origin_country')->pluck('origin_country')->filter()->values(),
                'body_types' => \App\Models\Vehicle::join('listings', 'listings.vehicle_id', '=', 'vehicles.id')
                    ->where('listings.publication_status', 'published')
                    ->distinct()->orderBy('body_type')->pluck('body_type')->filter()->values(),
                'years'      => range(date('Y'), 2005),
            ];
        });
    }

    /**
     * Autocomplétion rapide (utilisée par l'input de recherche live).
     */
    public function autocomplete(string $query, int $limit = 8): array
    {
        if (strlen($query) < 2) return [];

        return Cache::remember('autocomplete_' . md5($query), 30, function () use ($query, $limit) {
            $makes = \App\Models\Vehicle::where('make', 'like', "{$query}%")
                ->distinct()->pluck('make')
                ->take(4)->map(fn ($m) => ['type' => 'make', 'label' => $m, 'value' => $m]);

            $models = \App\Models\Vehicle::where('model', 'like', "{$query}%")
                ->distinct()->select('make', 'model')
                ->get()->take(4)
                ->map(fn ($v) => ['type' => 'model', 'label' => "{$v->make} {$v->model}", 'value' => $v->model, 'make' => $v->make]);

            $listings = Listing::published()
                ->where('title', 'like', "%{$query}%")
                ->take(4)->get()
                ->map(fn ($l) => ['type' => 'listing', 'label' => $l->title, 'value' => $l->slug, 'url' => route('vehicles.show', $l)]);

            return $makes->merge($models)->merge($listings)->take($limit)->values()->toArray();
        });
    }
}
