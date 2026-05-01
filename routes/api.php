<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PublicCatalogController;
use App\Http\Controllers\Api\PublicVehicleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AppBidController;
use App\Http\Controllers\Api\AppFavoriteController;
use App\Http\Controllers\Api\AppAlertController;
use App\Http\Controllers\Api\AppPurchaseController;
use App\Http\Controllers\Api\AuctionStateController;
use App\Http\Middleware\EnsureAuctionPriceAccess;

// API publique
Route::prefix('public')->group(function () {
    Route::get('/home', [PublicCatalogController::class, 'home']);
    Route::get('/catalog', [PublicCatalogController::class, 'index']);
    Route::get('/catalog/filters', [PublicCatalogController::class, 'filters']);
    Route::get('/vehicles/{listing}', [PublicVehicleController::class, 'show']);
    Route::get('/vehicles/{listing}/similar', [PublicVehicleController::class, 'similar']);
    Route::get('/vehicles/{listing}/auction-state', [AuctionStateController::class, 'show']);
    Route::get('/vehicles/{listing}/pricing', [PublicVehicleController::class, 'pricing'])
        ->middleware(EnsureAuctionPriceAccess::class);

    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
});

// API connectée
Route::middleware('auth:sanctum')->prefix('app')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/favorites', [AppFavoriteController::class, 'index']);
    Route::post('/favorites/{listing}', [AppFavoriteController::class, 'store']);
    Route::delete('/favorites/{listing}', [AppFavoriteController::class, 'destroy']);

    Route::get('/bids', [AppBidController::class, 'index']);
    Route::post('/vehicles/{listing}/bid', [AppBidController::class, 'store']);
    Route::patch('/bids/{bid}', [AppBidController::class, 'update']);
    Route::delete('/bids/{bid}', [AppBidController::class, 'destroy']);

    Route::post('/vehicles/{listing}/buy-now', [AppPurchaseController::class, 'buyNow']);
    Route::get('/vehicles/{listing}/pricing', [AppPurchaseController::class, 'pricing']);
    Route::post('/vehicles/{listing}/deposit-intent', [AppPurchaseController::class, 'depositIntent']);
    Route::post('/vehicles/{listing}/deposit-checkout', [AppPurchaseController::class, 'depositCheckout']);

    Route::get('/alerts', [AppAlertController::class, 'index']);
    Route::post('/alerts', [AppAlertController::class, 'store']);
    Route::delete('/alerts/{savedSearch}', [AppAlertController::class, 'destroy']);
});
