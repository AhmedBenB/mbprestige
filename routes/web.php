<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\CatalogController;
use App\Http\Controllers\Public\VehicleController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Webhooks\StripeWebhookController;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/catalogue', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/encheres', [CatalogController::class, 'auctions'])->name('catalog.auctions');
Route::get('/prix-fixes', [CatalogController::class, 'fixedPrices'])->name('catalog.fixed_prices');
Route::get('/stock', [CatalogController::class, 'stock'])->name('catalog.stock');

Route::get('/vehicules/{listing:slug}', [VehicleController::class, 'show'])->name('vehicles.show');

Route::get('/marques', [PageController::class, 'brands'])->name('brands.index');
Route::get('/marques/{make}', [PageController::class, 'brand'])->name('brands.show');
Route::get('/marques/{make}/{model}', [PageController::class, 'model'])->name('brands.model');
Route::get('/marques/{make}/{model}/{year}', [PageController::class, 'modelYear'])->name('brands.model_year');
Route::get('/pays/{country}', [PageController::class, 'country'])->name('countries.show');

Route::view('/comment-ca-marche', 'public.pages.how-it-works')->name('how_it_works');
Route::view('/frais', 'public.pages.costs')->name('costs');
Route::view('/livraison', 'public.pages.delivery')->name('delivery');
Route::view('/inspection', 'public.pages.inspection')->name('inspection');
Route::view('/faq', 'public.pages.faq')->name('faq');
Route::view('/professionnels', 'public.pages.professionals')->name('professionals');
Route::view('/contact', 'public.pages.contact')->name('contact');
Route::view('/mentions-legales', 'public.pages.mentions-legales')->name('mentions_legales');
Route::view('/conditions-generales', 'public.pages.conditions-generales')->name('cgv');
Route::view('/confidentialite', 'public.pages.confidentialite')->name('privacy');

Route::post('/stripe/webhook', StripeWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('stripe.webhook');

require __DIR__ . '/auth.php';
require __DIR__ . '/app.php';
require __DIR__ . '/admin.php';
