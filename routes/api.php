<?php

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

// Extension API
// TODO: Replace with JWT auth when implemented
Route::prefix('extension')->middleware(['web', 'auth'])->group(function () {
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
