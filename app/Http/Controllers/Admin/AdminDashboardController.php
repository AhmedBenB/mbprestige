<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatusEnum;
use App\Enums\PublicationStatusEnum;
use App\Enums\PurchaseStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\ExternalListing;
use App\Models\ExternalListingBid;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\SavedSearch;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $kpis = [
            'clients' => User::query()->whereIn('role', ['client', 'user'])->count(),
            'users_total' => User::query()->count(),
            'listings_published' => Listing::query()->where('publication_status', PublicationStatusEnum::Published)->count(),
            'external_listings_published' => ExternalListing::query()->where('status', ExternalListing::STATUS_PUBLISHED)->count(),
            'external_live_auctions' => ExternalListing::query()
                ->where('status', ExternalListing::STATUS_PUBLISHED)
                ->where('listing_type', 'like', 'auction_%')
                ->where(function ($query): void {
                    $query->whereNull('auction_end_at')
                        ->orWhere('auction_end_at', '>', now());
                })
                ->count(),
            'external_bids_total' => ExternalListingBid::query()->count(),
            'external_bidders_unique' => ExternalListingBid::query()->distinct('user_id')->count('user_id'),
            'external_views_total' => ExternalListing::query()->sum('views_count'),
            'reservations_active' => Purchase::query()->whereIn('status', [
                PurchaseStatusEnum::Reserved,
                PurchaseStatusEnum::DepositPending,
            ])->count(),
            'deposits_paid' => Payment::query()
                ->where('status', PaymentStatusEnum::Paid)
                ->where('type', 'deposit')
                ->count(),
            'bids_active' => Bid::query()->whereIn('status', ['pending', 'leading'])->count(),
            'client_requests' => SavedSearch::query()->count(),
            'support_tickets_open' => SupportTicket::query()->whereNotIn('status', [
                'resolved',
                'closed',
            ])->count(),
        ];

        $topViewedListings = ExternalListing::query()
            ->where('status', ExternalListing::STATUS_PUBLISHED)
            ->orderByDesc('views_count')
            ->limit(8)
            ->get(['id', 'slug', 'title', 'make', 'model', 'views_count']);

        $topBidListings = ExternalListing::query()
            ->withCount('bids')
            ->where('status', ExternalListing::STATUS_PUBLISHED)
            ->orderByDesc('bids_count')
            ->limit(8)
            ->get(['id', 'slug', 'title', 'make', 'model']);

        $latestExternalBids = ExternalListingBid::query()
            ->with(['user:id,name,email', 'listing:id,title,slug,make,model'])
            ->latest('placed_at')
            ->limit(10)
            ->get();

        $popularMakes = ExternalListing::query()
            ->selectRaw('make, COUNT(*) as total')
            ->where('status', ExternalListing::STATUS_PUBLISHED)
            ->whereNotNull('make')
            ->groupBy('make')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $popularModels = ExternalListing::query()
            ->selectRaw('model, COUNT(*) as total')
            ->where('status', ExternalListing::STATUS_PUBLISHED)
            ->whereNotNull('model')
            ->where('model', '!=', '')
            ->groupBy('model')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $latestPurchases = Purchase::query()
            ->with(['user', 'listing'])
            ->latest()
            ->limit(8)
            ->get();

        $latestPayments = Payment::query()
            ->with(['user', 'listing'])
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard.index', compact(
            'kpis',
            'latestPurchases',
            'latestPayments',
            'topViewedListings',
            'topBidListings',
            'latestExternalBids',
            'popularMakes',
            'popularModels',
        ));
    }
}
