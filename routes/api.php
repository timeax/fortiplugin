<?php

use Illuminate\Support\Facades\Route;
use Timeax\FortiPlugin\Http\Controllers\AuthController;
use Timeax\FortiPlugin\Http\Controllers\PackagerController;

Route::prefix('forti')->name('forti.')->middleware('forti.token')->group(function () {
    // Auth (login does not need token)
    Route::post('/login', [AuthController::class, 'login'])->withoutMiddleware('forti.token')->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Packager
    Route::get('/handshake', [PackagerController::class, 'handshake'])->name('handshake');          // fetch policy/verify key (author token ok)
    Route::post('/handshake/init', [PackagerController::class, 'init'])->name('handshake.init');          // create placeholder + issue plugin token
    Route::post('/pack', [PackagerController::class, 'pack'])->name('pack');                    // sign plugin_key (usually needs placeholder token)
});