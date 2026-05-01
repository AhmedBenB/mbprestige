<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BidController;
use App\Http\Controllers\Admin\ClientRequestController;
use App\Http\Controllers\Admin\ListingController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Annonces
    Route::get('listings', [ListingController::class, 'index'])->name('listings.index');
    Route::get('listings/{listing}', [ListingController::class, 'show'])->name('listings.show');
    Route::patch('listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
    Route::post('listings/{listing}/approve', [ListingController::class, 'approve'])->name('listings.approve');
    Route::post('listings/{listing}/publish', [ListingController::class, 'publish'])->name('listings.publish');
    Route::post('listings/{listing}/pause', [ListingController::class, 'pause'])->name('listings.pause');
    Route::post('listings/{listing}/archive', [ListingController::class, 'archive'])->name('listings.archive');
    Route::post('listings/{listing}/reprocess-media', [ListingController::class, 'reprocessMedia'])->name('listings.reprocess_media');

    // Ventes / réservations
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
    Route::post('purchases/{purchase}/mark-deposit-paid', [PurchaseController::class, 'markDepositPaid'])->name('purchases.mark_deposit_paid');
    Route::post('purchases/{purchase}/mark-completed', [PurchaseController::class, 'markCompleted'])->name('purchases.mark_completed');

    // Paiements
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('payments/{payment}/mark-paid', [PaymentController::class, 'markPaid'])->name('payments.mark_paid');
    Route::post('payments/{payment}/mark-failed', [PaymentController::class, 'markFailed'])->name('payments.mark_failed');
    Route::post('payments/{payment}/mark-refunded', [PaymentController::class, 'markRefunded'])->name('payments.mark_refunded');

    // Offres & clients
    Route::get('bids', [BidController::class, 'index'])->name('bids.index');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::get('client-requests', [ClientRequestController::class, 'index'])->name('client_requests.index');

    // Support client
    Route::get('support-tickets', [SupportTicketController::class, 'index'])->name('support.index');
    Route::get('support-tickets/{supportTicket}', [SupportTicketController::class, 'show'])->name('support.show');
    Route::post('support-tickets/{supportTicket}/reply', [SupportTicketController::class, 'reply'])->name('support.reply');
    Route::post('support-tickets/{supportTicket}/status', [SupportTicketController::class, 'updateStatus'])->name('support.status');
});
