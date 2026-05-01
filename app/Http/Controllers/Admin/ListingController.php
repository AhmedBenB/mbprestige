<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListingUpdateRequest;
use App\Models\Listing;
use App\Services\Listings\ListingPublicationService;
use App\Jobs\ProcessListingImagesJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListingController extends Controller
{
    public function __construct(private readonly ListingPublicationService $publicationService) {}

    public function index(Request $request): View
    {
        $listings = Listing::query()
            ->with(['vehicle', 'source', 'coverImage'])
            ->when($request->status, fn ($q) => $q->where('publication_status', $request->status))
            ->when($request->type,   fn ($q) => $q->where('listing_type', $request->type))
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.listings.index', compact('listings'));
    }

    public function show(Listing $listing): View
    {
        $listing->load(['vehicle', 'source', 'images', 'documents', 'attributes', 'auction', 'bids.user']);
        return view('admin.listings.show', compact('listing'));
    }

    public function update(ListingUpdateRequest $request, Listing $listing): RedirectResponse
    {
        $listing->update($request->validated());
        return back()->with('success', 'Annonce mise à jour.');
    }

    public function approve(Listing $listing): RedirectResponse
    {
        try {
            $this->publicationService->approve($listing);
            return back()->with('success', 'Annonce approuvée.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function publish(Listing $listing): RedirectResponse
    {
        try {
            $this->publicationService->publish($listing);
            return back()->with('success', 'Annonce publiée.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function pause(Listing $listing): RedirectResponse
    {
        try {
            $this->publicationService->pause($listing);
            return back()->with('success', 'Annonce mise en pause.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function archive(Listing $listing): RedirectResponse
    {
        $this->publicationService->archive($listing);
        return back()->with('success', 'Annonce archivée.');
    }

    public function reprocessMedia(Listing $listing): RedirectResponse
    {
        $listing->images()->update(['processing_status' => 'pending']);
        $listing->update(['publication_status' => 'media_processing']);
        dispatch(new ProcessListingImagesJob($listing->id));
        return back()->with('success', 'Traitement médias relancé.');
    }
}
