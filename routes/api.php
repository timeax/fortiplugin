<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Timeax\FortiPlugin\Http\Controllers\AuthController;
use Timeax\FortiPlugin\Http\Controllers\PackagerController;
use Timeax\FortiPlugin\Http\Controllers\Ui\EmbedResolveController;
use Timeax\FortiPlugin\Http\Middleware\FortiTokenGuard;


// --- Main 'forti' Route Group ---
// All routes under 'forti' prefix, with 'forti.' name prefix, 'web' middleware,
// and protected by the custom FortiTokenGuard.
Route::prefix('forti')
    ->name('forti.')
    ->middleware(['web', FortiTokenGuard::class])
    ->withoutMiddleware(VerifyCsrfToken::class) // Skip CSRF check for this API/Plugin-like flow
    ->group(function () {

        // 🛡️ Authentication Routes
        // These handle login/logout, and the 'login' route must bypass the token guard.
        Route::group(['without' => [FortiTokenGuard::class]], static function () {
            // Login must be outside the token guard to get a token!
            Route::post('/login', [AuthController::class, 'login'])->name('login');
        });
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');


        // 🤝 Handshake & Initialization Routes (Low-Level Communication)
        // These are typically for connection health and bootstrapping.
        Route::get('/handshake', [PackagerController::class, 'handshake'])->name('handshake');
        Route::post('/handshake/init', [PackagerController::class, 'init'])->name('handshake.init');


        // 📦 Packaging Flow Routes
        // The four-step process for artifact creation and upload.
        Route::prefix('pack')->name('pack.')->group(function () {
            Route::post('/handshake', [PackagerController::class, 'packHandshake'])->name('handshake'); // prepare
            Route::post('/manifest', [PackagerController::class, 'packManifest'])->name('manifest');   // sign and issue upload token
            Route::post('/upload', [PackagerController::class, 'packUpload'])->name('upload');       // receive artifact, server-side validate
            Route::post('/complete', [PackagerController::class, 'packComplete'])->name('complete');   // finalize
        });

//        Route::controller(PluginInstallController::class)
//            ->prefix('plugin')
//            ->name('plugin.')
//            ->group(function () {
//                Route::post('{zip}/install', 'queueInstall')->name('install');
//                Route::post('{zip}/activate', 'queueActivate')->name('activate');
//            });

        // 🔍 Utility/Diagnostic Routes
        Route::get('/structure', [PackagerController::class, 'getStructure'])->name('get-structure');
    });

Route::middleware(['web'])->get('/__forti/ui/embed/resolve', EmbedResolveController::class);
