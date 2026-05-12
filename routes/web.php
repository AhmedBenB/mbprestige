<?php

use App\Http\Controllers\ClientEmailVerificationController;
use App\Http\Controllers\Public\CatalogController as PublicCatalogController;
use App\Http\Controllers\Public\HomeController as PublicHomeController;
use App\Http\Controllers\Public\VehicleController as PublicVehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicHomeController::class, 'index'])->name('home');

Route::get('/demande', function () {
    $path = public_path('sourcing_auto_accueil_formulaire.html');
    return file_exists($path) ? response()->file($path) : redirect('/');
});

Route::get('/espace-client', function () {
    $path = public_path('dashboard_client_sourcing.html');
    return file_exists($path) ? response()->file($path) : redirect('/');
});

Route::get('/espace-garage', function () {
    $path = public_path('dashboard_admin_sourcing.html');
    return file_exists($path) ? response()->file($path) : redirect('/');
});

Route::get('/mentions-legales', function () {
    $path = public_path('mentions-legales.html');
    return file_exists($path) ? response()->file($path) : redirect('/');
})->name('mentions_legales');

Route::get('/confidentialite', function () {
    $path = public_path('politique-confidentialite.html');
    return file_exists($path) ? response()->file($path) : redirect('/');
})->name('privacy');

Route::get('/contact', function () {
    $path = public_path('contact.html');
    return file_exists($path) ? response()->file($path) : redirect('/#contact');
})->name('contact');

Route::get('/cgv', function () {
    return redirect()->route('mentions_legales');
})->name('cgv');

Route::get('/admin/imports/ecarstrade', function () {
    $path = public_path('admin_imports_ecarstrade.html');
    return file_exists($path) ? response()->file($path) : redirect('/espace-garage');
});

Route::get('/comment-ca-marche', function () {
    return redirect('/#comment-ca-marche');
})->name('how_it_works');

Route::get('/professionnels', function () {
    return redirect('/#professionnels');
})->name('professionals');

Route::get('/marques', function () {
    return redirect()->route('catalog.index');
})->name('brands.index');

Route::get('/frais-et-commissions', function () {
    return redirect('/#comment-ca-marche');
})->name('costs');

Route::get('/livraison', function () {
    return redirect('/#comment-ca-marche');
})->name('delivery');

Route::get('/faq', function () {
    return redirect('/#comment-ca-marche');
})->name('faq');

Route::get('/catalogue', [PublicCatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogue/encheres', fn () => redirect()->route('catalog.index', ['mode' => 'auctions']))->name('catalog.auctions');
Route::get('/catalogue/prix-fixes', fn () => redirect()->route('catalog.index', ['mode' => 'fixed_prices']))->name('catalog.fixed_prices');
Route::get('/catalogue/stock', fn () => redirect()->route('catalog.index', ['mode' => 'stock']))->name('catalog.stock');

Route::get('/vehicules/{listing}', [PublicVehicleController::class, 'show'])->name('vehicles.show');

Route::get('/client/email/verify', [ClientEmailVerificationController::class, 'notice'])
    ->name('verification.notice');

Route::get('/client/email/verify/{id}/{hash}', [ClientEmailVerificationController::class, 'verify'])
    ->name('verification.verify');

Route::redirect('/dashboard_admin_sourcing.html', '/espace-garage', 301);
Route::redirect('/login.html', '/connexion', 301);

require __DIR__ . '/auth.php';
