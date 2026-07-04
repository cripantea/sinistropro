<?php

use App\Http\Controllers\AllegatoController;
use App\Http\Controllers\PraticaNotaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    // Allegati: upload (legato alla pratica)
    Route::post('/pratiche/{pratica}/allegati', [AllegatoController::class, 'store'])
        ->name('api.allegati.store');

    // Allegati: pre-signed URL download ed eliminazione
    Route::get('/allegati/{allegato}/download', [AllegatoController::class, 'download'])
        ->name('api.allegati.download');

    Route::delete('/allegati/{allegato}', [AllegatoController::class, 'destroy'])
        ->name('api.allegati.destroy');

    // Note pratiche
    Route::post('/pratiche/{pratica}/note', [PraticaNotaController::class, 'store'])
        ->name('api.pratiche.note.store');
    Route::delete('/pratiche/{pratica}/note/{nota}', [PraticaNotaController::class, 'destroy'])
        ->name('api.pratiche.note.destroy');
});
