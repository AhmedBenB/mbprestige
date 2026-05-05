<?php

use App\Http\Controllers\ClientEmailVerificationController;
use App\Http\Controllers\PublicExternalListingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

Route::get('/connexion', function () {
    return response()->file(public_path('connexion_client_sourcing.html'));
});

Route::get('/inscription', function () {
    return redirect('/connexion?view=register');
});

Route::get('/demande', function () {
    return response()->file(public_path('sourcing_auto_accueil_formulaire.html'));
});

Route::get('/espace-client', function () {
    return response()->file(public_path('dashboard_client_sourcing.html'));
});

Route::get('/espace-garage', function () {
    return response()->file(public_path('dashboard_admin_sourcing.html'));
});

Route::get('/mentions-legales', function () {
    return response()->file(public_path('mentions-legales.html'));
});

Route::get('/confidentialite', function () {
    return response()->file(public_path('politique-confidentialite.html'));
});

Route::get('/contact', function () {
    return response()->file(public_path('contact.html'));
});

Route::get('/admin/imports/ecarstrade', function () {
    return response()->file(public_path('admin_imports_ecarstrade.html'));
});

Route::get('/comment-ca-marche', function () {
    return redirect('/#comment-ca-marche');
});

Route::get('/catalogue', function () {
    return redirect('/demande');
});

Route::get('/vehicules/{identifier}', [PublicExternalListingController::class, 'show']);

Route::get('/client/email/verify', [ClientEmailVerificationController::class, 'notice'])
    ->name('verification.notice');

Route::get('/client/email/verify/{id}/{hash}', [ClientEmailVerificationController::class, 'verify'])
    ->name('verification.verify');
