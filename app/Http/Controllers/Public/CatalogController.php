<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ExternalListing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    private float $displayMargin;
    /** @var array<string, array<int, string>> */
    private array $fuelVariants = [
        'diesel' => ['diesel'],
        'essence' => ['essence', 'petrol', 'benzine'],
        'hybride' => ['hybride', 'hybrid', 'hybride (diesel / electrique)', 'hybride (essence / electrique)'],
        'electrique' => ['electrique', 'électrique', 'electric', 'ev', 'ã©lectrique'],
        'gpl' => ['gpl', 'lpg'],
        'gaz' => ['gaz', 'gas', 'cng'],
    ];
    /** @var array<string, array<int, string>> */
    private array $gearboxVariants = [
        'automatic' => ['automatic', 'automatique', 'auto', 'aut8'],
        'manual' => ['manual', 'manuel', 'manuelle'],
        'semi-automatic' => ['semi-automatic', 'semi automatic', 'semi automatique', 'direct no gearbox'],
    ];

    public function __construct()
    {
        $this->displayMargin = (float) config('ecarstrade.import.display_margin', config('ecarstrade.import.margin_min', 2000));
    }

    public function index(Request $request): View
    {
        $listings = $this->applyFilters($request, $this->baseQuery($request))
            ->paginate(24)
            ->withQueryString();

        $filters = $this->getFilterOptions();

        return view('public.catalogue.index', compact('listings', 'filters'));
    }

    public function auctions(Request $request): View
    {
        $request->merge(['mode' => 'auctions']);
        $listings = $this->applyFilters($request, $this->baseQuery($request))
            ->paginate(24)
            ->withQueryString();

        $filters = $this->getFilterOptions();

        return view('public.catalogue.auctions', compact('listings', 'filters'));
    }

    public function fixedPrices(Request $request): View
    {
        $request->merge(['mode' => 'fixed_prices']);
        $listings = $this->applyFilters($request, $this->baseQuery($request))
            ->paginate(24)
            ->withQueryString();

        $filters = $this->getFilterOptions();

        return view('public.catalogue.prix-fixes', compact('listings', 'filters'));
    }

    public function stock(Request $request): View
    {
        $request->merge(['mode' => 'stock']);
        $listings = $this->applyFilters($request, $this->baseQuery($request))
            ->paginate(24)
            ->withQueryString();

        $filters = $this->getFilterOptions();

        return view('public.catalogue.stock', compact('listings', 'filters'));
    }

    private function baseQuery(Request $request)
    {
        $query = ExternalListing::query()
            ->with('latestPriceEstimate')
            ->whereIn('status', [ExternalListing::STATUS_PUBLISHED, ExternalListing::STATUS_EXPIRED]);

        return match ((string) $request->get('mode', '')) {
            'auctions' => $query->where(function ($q): void {
                $q->where('listing_type', 'like', 'auction_%')
                    ->orWhere(function ($q2): void {
                        $q2->whereNotNull('auction_end_at')
                            ->where('listing_type', '!=', 'fixed_price');
                    });
            }),
            'fixed_prices' => $query->where(function ($q): void {
                $q->where('listing_type', 'fixed_price')
                    ->orWhere(function ($q2): void {
                        $q2->where('price_visible', true)
                            ->whereNull('auction_end_at')
                            ->where('listing_type', 'not like', 'auction_%');
                    });
            }),
            'stock' => $query->where('listing_type', 'partner_stock'),
            default => $query,
        };
    }

    private function applyFilters(Request $request, $query)
    {
        $fuel = $this->normalizeFuel((string) $request->get('fuel', ''));
        $gearbox = $this->normalizeGearbox((string) $request->get('gearbox', ''));

        return $query
            ->when($request->make, fn ($q) => $q->where('make', $request->make))
            ->when($request->model, fn ($q) => $q->where('model', $request->model))
            ->when($fuel !== null, function ($q) use ($fuel): void {
                $variants = $this->fuelVariants[$fuel] ?? [$fuel];
                $q->where(function ($q2) use ($variants): void {
                    foreach ($variants as $value) {
                        $q2->orWhereRaw('LOWER(COALESCE(fuel, "")) = ?', [mb_strtolower($value)]);
                    }
                });
            })
            ->when($gearbox !== null, function ($q) use ($gearbox): void {
                $variants = $this->gearboxVariants[$gearbox] ?? [$gearbox];
                $q->where(function ($q2) use ($variants): void {
                    foreach ($variants as $value) {
                        $q2->orWhereRaw('LOWER(COALESCE(transmission, "")) = ?', [mb_strtolower($value)]);
                    }
                });
            })
            ->when($request->year_min, fn ($q) => $q->where('year', '>=', (int) $request->year_min))
            ->when($request->year_max, fn ($q) => $q->where('year', '<=', (int) $request->year_max))
            ->when($request->km_max, fn ($q) => $q->where('mileage', '<=', (int) $request->km_max))
            ->when($request->price_min, fn ($q) => $q->whereRaw('(price_amount + ?) >= ?', [$this->displayMargin, (float) $request->price_min]))
            ->when($request->price_max, fn ($q) => $q->whereRaw('(price_amount + ?) <= ?', [$this->displayMargin, (float) $request->price_max]))
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END ASC", [ExternalListing::STATUS_PUBLISHED])
            ->orderBy($this->getSortColumn($request->sort), $this->getSortDirection($request->sort));
    }

    private function getSortColumn(?string $sort = null): string
    {
        return match($sort) {
            'price_asc', 'price_desc'    => 'price_amount',
            'year_desc', 'year_asc'      => 'year',
            'ends_at'                    => 'auction_end_at',
            default                      => 'published_at',
        };
    }

    private function getSortDirection(?string $sort = null): string
    {
        return match($sort) {
            'price_asc', 'year_asc' => 'asc',
            default                 => 'desc',
        };
    }

    private function getFilterOptions(): array
    {
        $base = ExternalListing::query()->whereIn('status', [ExternalListing::STATUS_PUBLISHED, ExternalListing::STATUS_EXPIRED]);
        $fuelValues = (clone $base)->whereNotNull('fuel')->distinct()->pluck('fuel');
        $gearboxValues = (clone $base)->whereNotNull('transmission')->distinct()->pluck('transmission');

        return [
            'makes'       => (clone $base)->whereNotNull('make')->distinct()->orderBy('make')->pluck('make'),
            'fuels'       => $fuelValues->map(fn ($v) => $this->normalizeFuel((string) $v))->filter()->unique()->sort()->values(),
            'gearboxes'   => $gearboxValues->map(fn ($v) => $this->normalizeGearbox((string) $v))->filter()->unique()->sort()->values(),
            'body_types'  => collect(),
            'years'       => range(date('Y'), 2005),
        ];
    }

    private function normalizeFuel(string $value): ?string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '' || in_array($value, ['-', 'n/a', 'na', 'other'], true)) {
            return null;
        }

        foreach ($this->fuelVariants as $canonical => $variants) {
            if (in_array($value, array_map('mb_strtolower', $variants), true)) {
                return $canonical;
            }
        }

        return null;
    }

    private function normalizeGearbox(string $value): ?string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '' || in_array($value, ['-', 'n/a', 'na', 'other'], true)) {
            return null;
        }

        foreach ($this->gearboxVariants as $canonical => $variants) {
            if (in_array($value, array_map('mb_strtolower', $variants), true)) {
                return $canonical;
            }
        }

        return null;
    }
}
