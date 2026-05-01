<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatusEnum;
use App\Enums\PublicationStatusEnum;
use App\Enums\PurchaseStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Bid;
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
            'clients' => User::query()->where('role', 'client')->count(),
            'listings_published' => Listing::query()->where('publication_status', PublicationStatusEnum::Published)->count(),
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

        return view('admin.dashboard.index', compact('kpis', 'latestPurchases', 'latestPayments'));
    }
}
