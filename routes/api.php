<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExtensionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    // Extension OAuth callback - returns simple HTML page
    // Token is passed in URL fragment (#token=xxx) for security
    Route::get('extension-callback', function () {
        return response('
            <!DOCTYPE html>
            <html>
            <head>
                <title>Login Successful</title>
                <style>
                    body { font-family: system-ui; background: #1e1b4b; color: white; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                    .card { text-align: center; padding: 2rem; background: rgba(255,255,255,0.1); border-radius: 1rem; }
                    .success { color: #22c55e; font-size: 3rem; }
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="success">✓</div>
                    <h2>Login Successful!</h2>
                    <p>This tab will close automatically...</p>
                </div>
            </body>
            </html>
        ', 200, ['Content-Type' => 'text/html']);
    });
});

// Auth routes (protected)
Route::prefix('auth')->middleware('auth:api')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

// Extension API (protected with JWT)
Route::prefix('extension')->middleware('auth:api')->group(function () {
    // Connection verification
    Route::get('verify', [ExtensionController::class, 'verify']);

    // Platform connection
    Route::post('connect', [ExtensionController::class, 'connect']);

    // Comment submission and scanning
    Route::post('comments', [ExtensionController::class, 'submitComments']);

    // Get pending deletions for extension to execute
    Route::get('pending-deletions', [ExtensionController::class, 'getPendingDeletions']);

    // Report deletion results
    Route::post('report-deletions', [ExtensionController::class, 'reportDeletions']);

    // Get filters for local matching
    Route::get('filters', [ExtensionController::class, 'getFilters']);
});
