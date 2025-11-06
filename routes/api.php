<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Timeax\FortiPlugin\Http\Controllers\AuthController;
use Timeax\FortiPlugin\Http\Controllers\PackagerController;
use Timeax\FortiPlugin\Http\Middleware\FortiTokenGuard;

Route::prefix('forti')->name('forti.')->middleware(['web', FortiTokenGuard::class])->withoutMiddleware(VerifyCsrfToken::class)->group(function () {
    // Auth
    Route::post('/login', [AuthController::class, 'login'])->withoutMiddleware(FortiTokenGuard::class)->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Simple handshake (+ signature block for local dev config)
    Route::get('/handshake', [PackagerController::class, 'handshake'])->name('handshake');
    // Placeholder + placeholder token bootstrap
    Route::post('/handshake/init', [PackagerController::class, 'init'])->name('handshake.init');

    // New packaging flow (4 steps)
    Route::post('/pack/handshake', [PackagerController::class, 'packHandshake'])->name('pack.handshake'); // prepare
    Route::post('/pack/manifest', [PackagerController::class, 'packManifest'])->name('pack.manifest');   // sign & issue upload token
    Route::post('/pack/upload', [PackagerController::class, 'packUpload'])->name('pack.upload');       // receive artifact, server-side validate
    Route::post('/pack/complete', [PackagerController::class, 'packComplete'])->name('pack.complete');   // finalize
    Route::get('/structure', [PackagerController::class, 'getStructure'])->name('get-structure');
});