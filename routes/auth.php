<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

if (! Route::has('login')) {
    Route::middleware('guest')->group(function () {
        Route::get('/connexion', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/connexion', [AuthController::class, 'login'])->name('login.post');

        Route::get('/inscription', [AuthController::class, 'showRegister'])->name('register');
        Route::post('/inscription', [AuthController::class, 'register'])->name('register.post');

        Route::get('/mot-de-passe-oublie', [AuthController::class, 'showForgotPassword'])->name('password.request');
        Route::post('/mot-de-passe-oublie', [AuthController::class, 'sendResetLink'])->name('password.email');

        Route::get('/reinitialiser/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
        Route::post('/reinitialiser', [AuthController::class, 'resetPassword'])->name('password.update');
    });

    Route::post('/deconnexion', [AuthController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');
}
