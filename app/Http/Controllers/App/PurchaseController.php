<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Enums\PublicationStatusEnum;
use App\Services\Purchases\PurchaseReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseReservationService $purchaseService
    ) {}

    public function buyNow(Listing $listing): RedirectResponse
    {
        abort_unless($listing->buy_now_price, 422, 'Pas de prix fixe disponible.');
        abort_unless(in_array($listing->publication_status, [
            PublicationStatusEnum::Published,
            PublicationStatusEnum::Reserved,
        ], true), 404, 'Ce véhicule n’est plus disponible.');

        try {
            $this->purchaseService->reserveBuyNow($listing, auth()->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('app.payment.show', $listing)
            ->with('success', 'Véhicule réservé. Réglez maintenant uniquement l’acompte.');
    }
}
