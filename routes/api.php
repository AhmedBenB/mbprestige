<?php

use App\Http\Controllers\AdminAssociationController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminEcarsTradeAccountController;
use App\Http\Controllers\AdminEcarsTradeImportController;
use App\Http\Controllers\AdminPartnerController;
use App\Http\Controllers\AdminSearchController;
use App\Http\Controllers\ClientAssociationController;
use App\Http\Controllers\ClientAuthController;
use App\Http\Controllers\ClientEmailVerificationController;
use App\Http\Controllers\ClientSearchController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PublicExternalListingController;
use App\Http\Controllers\PublicRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function () {
    Route::get('/catalog/makes-models', [PublicRequestController::class, 'catalog']);
    Route::get('/garages', [PublicRequestController::class, 'garages']);
    Route::get('/partners/{slug}', [PublicRequestController::class, 'partner']);
    Route::get('/listings/{identifier}', [PublicExternalListingController::class, 'showApi']);
    Route::post('/requests', [PublicRequestController::class, 'store'])->middleware('throttle:public-request');
    Route::get('/requests/{token}', [PublicRequestController::class, 'show']);
    Route::patch('/requests/{token}', [PublicRequestController::class, 'update']);
    Route::get('/unsubscribe/email', [PublicRequestController::class, 'unsubscribeEmail']);
    Route::post('/unsubscribe/email', [PublicRequestController::class, 'unsubscribeEmail']);
    Route::get('/unsubscribe/sms', [PublicRequestController::class, 'unsubscribeSms']);
    Route::post('/unsubscribe/sms', [PublicRequestController::class, 'unsubscribeSms']);
});

Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login');
    Route::post('/forgot-password', [PasswordResetController::class, 'requestAdminLink'])->middleware('throttle:admin-forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'resetAdminPassword'])->middleware('throttle:admin-reset-password');

    Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::patch('/account/profile', [AdminAuthController::class, 'updateProfile']);
        Route::patch('/account/password', [AdminAuthController::class, 'updatePassword']);
        Route::get('/integrations/ecarstrade', [AdminEcarsTradeAccountController::class, 'show']);
        Route::patch('/integrations/ecarstrade', [AdminEcarsTradeAccountController::class, 'update']);
        Route::post('/integrations/ecarstrade/test', [AdminEcarsTradeAccountController::class, 'test']);
        Route::get('/imports/ecarstrade', [AdminEcarsTradeImportController::class, 'index']);
        Route::post('/imports/ecarstrade/run', [AdminEcarsTradeImportController::class, 'run']);
        Route::post('/imports/ecarstrade/enqueue', [AdminEcarsTradeImportController::class, 'enqueue']);
        Route::post('/imports/ecarstrade/listings/{listing}/publish', [AdminEcarsTradeImportController::class, 'publish']);
        Route::post('/imports/ecarstrade/listings/{listing}/do-not-publish', [AdminEcarsTradeImportController::class, 'doNotPublish']);
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/settings', [AdminDashboardController::class, 'settings']);
        Route::put('/settings', [AdminDashboardController::class, 'updateSettings']);
        Route::patch('/settings', [AdminDashboardController::class, 'updateSettings']);
        Route::get('/partners', [AdminPartnerController::class, 'index']);
        Route::post('/partners', [AdminPartnerController::class, 'store']);
        Route::delete('/partners/{partner}', [AdminPartnerController::class, 'destroy']);
        Route::get('/association-codes', [AdminAssociationController::class, 'codes']);
        Route::post('/association-codes', [AdminAssociationController::class, 'storeCode']);
        Route::get('/association-requests', [AdminAssociationController::class, 'requests']);
        Route::patch('/association-requests/{associationRequest}', [AdminAssociationController::class, 'review']);

        Route::get('/searches', [AdminSearchController::class, 'index']);
        Route::post('/searches', [AdminSearchController::class, 'store']);
        Route::get('/searches/{search}', [AdminSearchController::class, 'show']);
        Route::patch('/searches/{search}', [AdminSearchController::class, 'update']);
        Route::delete('/searches/{search}', [AdminSearchController::class, 'destroy']);
        Route::post('/searches/{search}/run', [AdminSearchController::class, 'run']);
        Route::get('/searches/{search}/results', [AdminSearchController::class, 'results']);
        Route::post('/search-runs/run-all', [AdminSearchController::class, 'runAll']);

        Route::get('/requests', [AdminSearchController::class, 'index']);
        Route::post('/requests', [AdminSearchController::class, 'store']);
        Route::get('/requests/{search}', [AdminSearchController::class, 'show']);
        Route::patch('/requests/{search}', [AdminSearchController::class, 'update']);
        Route::delete('/requests/{search}', [AdminSearchController::class, 'destroy']);
        Route::post('/requests/{search}/run', [AdminSearchController::class, 'run']);
        Route::post('/requests/{search}/pause', [AdminSearchController::class, 'pause']);
        Route::post('/requests/{search}/resume', [AdminSearchController::class, 'resume']);
        Route::post('/requests/{search}/close', [AdminSearchController::class, 'close']);
        Route::get('/requests/{search}/matches', [AdminSearchController::class, 'matches']);

        Route::get('/matches', [AdminSearchController::class, 'allMatches']);
        Route::get('/matches/{result}', [AdminSearchController::class, 'showMatch']);
        Route::patch('/matches/{result}', [AdminSearchController::class, 'updateMatchStatus']);
        Route::post('/matches/{result}/approve', [AdminSearchController::class, 'approve']);
        Route::post('/matches/{result}/reject', [AdminSearchController::class, 'reject']);
        Route::post('/matches/{result}/hold', [AdminSearchController::class, 'hold']);
        Route::post('/matches/{result}/mark-shared', [AdminSearchController::class, 'markShared']);
    });
});

Route::prefix('client')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register'])->middleware('throttle:client-register');
    Route::post('/claim-account', [ClientAuthController::class, 'claim'])->middleware('throttle:client-claim');
    Route::post('/login', [ClientAuthController::class, 'login'])->middleware('throttle:client-login');
    Route::post('/forgot-password', [PasswordResetController::class, 'requestClientLink'])->middleware('throttle:client-forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'resetClientPassword'])->middleware('throttle:client-reset-password');

    Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
        Route::post('/logout', [ClientAuthController::class, 'logout']);
        Route::get('/me', [ClientAuthController::class, 'me']);
        Route::post('/email/verification-notification', [ClientEmailVerificationController::class, 'resend'])->middleware('throttle:client-email-verification-resend');
    });

    Route::middleware(['auth:sanctum', 'role:client', 'client.verified'])->group(function () {
        Route::patch('/account/profile', [ClientAuthController::class, 'updateProfile']);
        Route::patch('/account/password', [ClientAuthController::class, 'updatePassword']);
        Route::post('/organization/attach-code', [ClientAuthController::class, 'attachOrganizationCode']);
        Route::get('/association-requests', [ClientAssociationController::class, 'index']);
        Route::post('/association-requests', [ClientAssociationController::class, 'store']);
        Route::get('/searches', [ClientSearchController::class, 'index']);
        Route::post('/searches', [ClientSearchController::class, 'store']);
        Route::get('/searches/{search}', [ClientSearchController::class, 'show']);
        Route::patch('/searches/{search}', [ClientSearchController::class, 'update']);
        Route::get('/searches/{search}/results', [ClientSearchController::class, 'results']);
    });
});
