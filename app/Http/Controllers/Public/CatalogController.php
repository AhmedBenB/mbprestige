<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $listings = $this->applyFilters($request, Listing::published())
            ->with(['vehicle', 'coverImage', 'auction'])
            ->paginate(24)
            ->withQueryString();

        $filters = $this->getFilterOptions();

        return view('public.catalogue.index', compact('listings', 'filters'));
    }

    public function auctions(Request $request): View
    {
        $listings = $this->applyFilters($request, Listing::published()->auctions())
            ->with(['vehicle', 'coverImage', 'auction'])
            ->orderBy('ends_at')
            ->paginate(24)
            ->withQueryString();

        $filters = $this->getFilterOptions();

        return view('public.catalogue.auctions', compact('listings', 'filters'));
    }

    public function fixedPrices(Request $request): View
    {
        $listings = $this->applyFilters($request, Listing::published()->fixedPrices())
            ->with(['vehicle', 'coverImage'])
            ->paginate(24)
            ->withQueryString();

        $filters = $this->getFilterOptions();

        return view('public.catalogue.prix-fixes', compact('listings', 'filters'));
    }

    public function stock(Request $request): View
    {
        $listings = $this->applyFilters($request, Listing::published()->where('listing_type', 'partner_stock'))
            ->with(['vehicle', 'coverImage'])
            ->paginate(24)
            ->withQueryString();

        $filters = $this->getFilterOptions();

        return view('public.catalogue.stock', compact('listings', 'filters'));
    }

    private function applyFilters(Request $request, $query)
    {
        return $query
            ->when($request->make, fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('make', $request->make)))
            ->when($request->model, fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('model', $request->model)))
            ->when($request->fuel, fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('fuel_type', $request->fuel)))
            ->when($request->gearbox, fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('gearbox', $request->gearbox)))
            ->when($request->country, fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('origin_country', $request->country)))
            ->when($request->year_min, fn ($q) => $q->whereHas('vehicle', fn ($v) =>
                $v->whereYear('first_registration_date', '>=', $request->year_min)))
            ->when($request->year_max, fn ($q) => $q->whereHas('vehicle', fn ($v) =>
                $v->whereYear('first_registration_date', '<=', $request->year_max)))
            ->when($request->km_max, fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('mileage', '<=', $request->km_max)))
            ->when($request->price_min, fn ($q) => $q->where(fn ($s) =>
                $s->where('buy_now_price', '>=', $request->price_min)
                  ->orWhere('current_bid', '>=', $request->price_min)))
            ->when($request->price_max, fn ($q) => $q->where(fn ($s) =>
                $s->where('buy_now_price', '<=', $request->price_max)
                  ->orWhere('current_bid', '<=', $request->price_max)))
            ->when($request->vat == '1', fn ($q) => $q->where('vat_deductible', true))
            ->when($request->body_type, fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('body_type', $request->body_type)))
            ->orderBy($this->getSortColumn($request->sort), $this->getSortDirection($request->sort));
    }

    private function getSortColumn(string $sort = null): string
    {
        return match($sort) {
            'price_asc', 'price_desc'    => 'buy_now_price',
            'year_desc', 'year_asc'      => 'starts_at', // approximation
            'ends_at'                    => 'ends_at',
            default                      => 'published_at',
        };
    }

    private function getSortDirection(string $sort = null): string
    {
        return match($sort) {
            'price_asc', 'year_asc' => 'asc',
            default                 => 'desc',
        };
    }

    private function getFilterOptions(): array
    {
        return [
            'makes'       => Vehicle::distinct()->orderBy('make')->pluck('make'),
            'fuels'       => Vehicle::distinct()->orderBy('fuel_type')->pluck('fuel_type')->filter(),
            'gearboxes'   => Vehicle::distinct()->orderBy('gearbox')->pluck('gearbox')->filter(),
            'body_types'  => Vehicle::distinct()->orderBy('body_type')->pluck('body_type')->filter(),
            'countries'   => Vehicle::distinct()->orderBy('origin_country')->pluck('origin_country')->filter(),
            'years'       => range(date('Y'), 2005),
        ];
    }
}
