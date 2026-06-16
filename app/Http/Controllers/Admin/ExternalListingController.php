<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalListing;
use App\Models\ExternalListingBid;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ExternalListingController extends Controller
{
    public function index(Request $request): View
    {
        $retentionDays = 7;
        $cutoff = now()->subDays($retentionDays);

        $listings = ExternalListing::query()
            ->with(['source', 'latestPriceEstimate'])
            ->withCount('bids')
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('listing_type', (string) $request->string('type')))
            ->when($request->filled('has_bids'), function ($query) use ($request): void {
                if ((string) $request->string('has_bids') === 'yes') {
                    $query->has('bids');
                }
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->string('search'));
                $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('external_id', 'like', "%{$search}%")
                        ->orWhere('make', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->latest('updated_at')
            ->paginate(40)
            ->withQueryString();

        $kpis = [
            'published' => ExternalListing::query()->where('status', ExternalListing::STATUS_PUBLISHED)->count(),
            'expired' => ExternalListing::query()->where('status', ExternalListing::STATUS_EXPIRED)->count(),
            'purgeable' => ExternalListing::query()
                ->where('status', ExternalListing::STATUS_EXPIRED)
                ->where(function ($query) use ($cutoff): void {
                    $query
                        ->whereNotNull('auction_end_at')
                        ->where('auction_end_at', '<=', $cutoff)
                        ->orWhere(function ($q) use ($cutoff): void {
                            $q->whereNull('auction_end_at')
                                ->where('updated_at', '<=', $cutoff);
                        });
                })
                ->count(),
            'bids_total' => ExternalListingBid::query()->count(),
            'retention_days' => $retentionDays,
            'sync_every_minutes' => (int) config('ecarstrade.import.sync_every_minutes', 30),
        ];

        return view('admin.external-listings.index', compact('listings', 'kpis'));
    }

    public function show(ExternalListing $externalListing): View
    {
        $externalListing->load([
            'source',
            'latestPriceEstimate',
            'documents',
            'bids.user.organization',
        ]);

        $payments = Payment::query()
            ->with(['user:id,name,email', 'organization:id,name'])
            ->where(function ($query) use ($externalListing): void {
                $query->where('metadata->external_listing_id', (string) $externalListing->id)
                    ->orWhere('metadata->external_listing_id', $externalListing->id)
                    ->orWhere('metadata->external_id', (string) $externalListing->external_id)
                    ->orWhere('metadata->external_listing_external_id', (string) $externalListing->external_id);
            })
            ->latest()
            ->get();

        return view('admin.external-listings.show', [
            'listing' => $externalListing,
            'payments' => $payments,
        ]);
    }

    public function runLifecycle(Request $request): RedirectResponse
    {
        $retentionDays = max(1, min(90, (int) $request->integer('retention_days', 7)));

        Artisan::call('ecarstrade:lifecycle', [
            '--retention-days' => $retentionDays,
        ]);

        $output = trim((string) Artisan::output());

        return back()->with('success', 'Cycle expiration/purge lance.')->with('lifecycle_output', $output);
    }
}
