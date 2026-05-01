<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Listings\AuctionPriceMasker;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCatalogController extends Controller
{
    public function __construct(
        private readonly SearchService $search,
        private readonly AuctionPriceMasker $priceMasker
    ) {}

    /** GET /api/public/catalog */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'q', 'make', 'model', 'fuel', 'gearbox', 'country', 'body_type',
            'listing_type', 'year_min', 'year_max', 'km_max',
            'price_min', 'price_max', 'vat', 'sort',
        ]);

        $results = $this->search->search(
            $filters,
            (int) $request->get('per_page', 24),
            (int) $request->get('page', 1)
        );

        $canViewAuctionPrices = (bool) ($request->user() ?? $request->user('sanctum'));
        $this->priceMasker->maskMany($results['listings']->getCollection(), $canViewAuctionPrices);

        return response()->json([
            'data'    => $results['listings']->items(),
            'meta'    => [
                'total'        => $results['total'],
                'per_page'     => $results['listings']->perPage(),
                'current_page' => $results['listings']->currentPage(),
                'last_page'    => $results['listings']->lastPage(),
                'engine'       => $results['engine'],
                'can_view_auction_prices' => $canViewAuctionPrices,
            ],
        ]);
    }

    /** GET /api/public/catalog/filters */
    public function filters(Request $request): JsonResponse
    {
        $facets = $this->search->buildFacets($request->all());
        return response()->json($facets);
    }

    /** GET /api/public/catalog/autocomplete?q=bmw */
    public function autocomplete(Request $request): JsonResponse
    {
        $suggestions = $this->search->autocomplete($request->get('q', ''));
        return response()->json($suggestions);
    }

    /** GET /api/public/home */
    public function home(Request $request): JsonResponse
    {
        $featured = Listing::published()->featured()->with(['vehicle','coverImage'])->take(8)->get();
        $latest = Listing::published()->with(['vehicle','coverImage'])->latest('published_at')->take(12)->get();
        $live = Listing::published()->auctions()->live()->with(['vehicle','coverImage','auction'])->orderBy('ends_at')->take(6)->get();

        $canViewAuctionPrices = (bool) ($request->user() ?? $request->user('sanctum'));
        $this->priceMasker->maskMany($featured, $canViewAuctionPrices);
        $this->priceMasker->maskMany($latest, $canViewAuctionPrices);
        $this->priceMasker->maskMany($live, $canViewAuctionPrices);

        return response()->json([
            'featured'  => $featured,
            'latest'    => $latest,
            'live'      => $live,
            'stats'     => [
                'total_vehicles' => Listing::published()->count(),
                'live_auctions'  => Listing::published()->auctions()->live()->count(),
                'brands'         => \App\Models\Vehicle::distinct('make')->count(),
            ],
            'meta'      => [
                'can_view_auction_prices' => $canViewAuctionPrices,
            ],
        ]);
    }
}
