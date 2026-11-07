<?php

// Fusion Kit — sorgente unica in fusion-id/kit/laravel (copiata da kit/sync.sh: non modificare qui).

use App\FusionKit\FusionKitController;
use Illuminate\Support\Facades\Route;

// Login con Fusion ID (con sessione e CSRF del gruppo web).
Route::middleware('web')->prefix('auth/fusion')->group(function () {
    Route::get('login', [FusionKitController::class, 'login'])->name('fusion.login');
    Route::get('callback', [FusionKitController::class, 'callback'])->name('fusion.callback');
    Route::get('impersonate', [FusionKitController::class, 'impersonate'])->name('fusion.impersonate');
});

// API per Fusion Control: senza sessione né CSRF, autenticate con FUSION_KIT_SECRET.
Route::prefix('api/fusion')->group(function () {
    Route::get('health', [FusionKitController::class, 'health']);
    Route::get('tenants', [FusionKitController::class, 'tenants']);
    Route::get('users', [FusionKitController::class, 'users']);
    Route::post('tenants/{id}/suspend', [FusionKitController::class, 'suspend']);
    Route::post('tenants/{id}/resume', [FusionKitController::class, 'resume']);
    Route::post('impersonate', [FusionKitController::class, 'impersonateLink']);
    Route::post('users/logout', [FusionKitController::class, 'revokeSessions']);
});
