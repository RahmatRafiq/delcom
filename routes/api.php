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
