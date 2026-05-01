<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\FavoriteController;
use App\Http\Controllers\App\BidController;
use App\Http\Controllers\App\PurchaseController;
use App\Http\Controllers\App\AlertController;
use App\Http\Controllers\App\ProfileController;
use App\Http\Controllers\App\VehicleController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\PaymentController;
use App\Http\Controllers\App\SupportTicketController;
use App\Http\Middleware\EnsureAuctionPriceAccess;

Route::middleware(['auth'])->prefix('app')->name('app.')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/favoris', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favoris/{listing}', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/favoris/{listing}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');

    Route::get('/offres', [BidController::class, 'index'])->name('bids.index');
    Route::post('/vehicules/{listing}/offres', [BidController::class, 'store'])->name('bids.store');
    Route::patch('/offres/{bid}', [BidController::class, 'update'])->name('bids.update');
    Route::delete('/offres/{bid}', [BidController::class, 'destroy'])->name('bids.destroy');

    Route::post('/vehicules/{listing}/achat-immediat', [PurchaseController::class, 'buyNow'])
        ->middleware(EnsureAuctionPriceAccess::class)
        ->name('purchase.buy_now');
    Route::get('/vehicules/{listing}/paiement', [PaymentController::class, 'show'])
        ->middleware(EnsureAuctionPriceAccess::class)
        ->name('payment.show');
    Route::post('/vehicules/{listing}/paiement/checkout', [PaymentController::class, 'createCheckout'])
        ->middleware(EnsureAuctionPriceAccess::class)
        ->name('payment.checkout');
    Route::post('/vehicules/{listing}/paiement/intent', [PaymentController::class, 'createIntent'])
        ->middleware(EnsureAuctionPriceAccess::class)
        ->name('payment.intent');
    Route::get('/vehicules/{listing}/paiement/succes', [PaymentController::class, 'success'])->name('payment.success');
    Route::get('/vehicules/{listing}/paiement/annule', [PaymentController::class, 'cancel'])->name('payment.cancel');
    Route::get('/vehicules/{listing}/paiement/en-attente', [PaymentController::class, 'pending'])->name('payment.pending');

    Route::get('/alertes', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alertes', [AlertController::class, 'store'])->name('alerts.store');
    Route::delete('/alertes/{savedSearch}', [AlertController::class, 'destroy'])->name('alerts.destroy');

    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/vehicules/{listing}', [VehicleController::class, 'show'])->name('vehicles.show');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/lu', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/tout-lire', [NotificationController::class, 'markAllRead'])->name('notifications.read_all');

    Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::get('/support/nouveau', [SupportTicketController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportTicketController::class, 'store'])->name('support.store');
    Route::get('/support/{supportTicket}', [SupportTicketController::class, 'show'])->name('support.show');
    Route::post('/support/{supportTicket}/reply', [SupportTicketController::class, 'reply'])->name('support.reply');
});
