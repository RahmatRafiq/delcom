<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\SocialAuthController;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $settings = AppSetting::getInstance();

    return Inertia::render('welcome', [
        'settings' => $settings,
    ]);
})->name('home');

// Legal Pages (Public)
Route::get('/privacy-policy', function () {
    return Inertia::render('Legal/PrivacyPolicy');
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return Inertia::render('Legal/TermsOfService');
})->name('terms-of-service');

// OAuth routes - order matters! Specific routes before wildcard
Route::get('auth/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback'])->name('auth.callback');
Route::get('auth/{provider}', [SocialAuthController::class, 'redirectToProvider'])->name('auth.redirect')
    ->where('provider', 'google|facebook|github'); // Only allow specific providers

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])
            ->middleware('permission:view-dashboard')
            ->name('dashboard');

        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

        Route::post('roles/json', [\App\Http\Controllers\UserRolePermission\RoleController::class, 'json'])->name('roles.json');
        Route::resource('roles', \App\Http\Controllers\UserRolePermission\RoleController::class)
            ->middleware('permission:view-roles|create-roles|edit-roles|delete-roles');

        Route::post('permissions/json', [\App\Http\Controllers\UserRolePermission\PermissionController::class, 'json'])->name('permissions.json');
        Route::resource('permissions', \App\Http\Controllers\UserRolePermission\PermissionController::class)
            ->middleware('permission:view-permissions|assign-permissions');

        Route::post('users/json', [\App\Http\Controllers\UserRolePermission\UserController::class, 'json'])->name('users.json');
        Route::resource('users', \App\Http\Controllers\UserRolePermission\UserController::class)
            ->middleware('permission:view-users|create-users|edit-users|delete-users');
        Route::get('users/trashed', [\App\Http\Controllers\UserRolePermission\UserController::class, 'trashed'])->name('users.trashed');
        Route::post('users/{user}/restore', [\App\Http\Controllers\UserRolePermission\UserController::class, 'restore'])->name('users.restore');
        Route::delete('users/{user}/force-delete', [\App\Http\Controllers\UserRolePermission\UserController::class, 'forceDelete'])->name('users.force-delete');

        Route::get('/app-settings', [\App\Http\Controllers\AppSettingController::class, 'index'])->name('app-settings.index');
        Route::put('/app-settings', [\App\Http\Controllers\AppSettingController::class, 'update'])->name('app-settings.update');

        Route::delete('/profile/delete-file', [\App\Http\Controllers\Settings\ProfileController::class, 'deleteFile'])->name('profile.deleteFile');
        Route::post('/profile/upload', [\App\Http\Controllers\Settings\ProfileController::class, 'upload'])->name('profile.upload');
        Route::post('/storage', [\App\Http\Controllers\StorageController::class, 'store'])->name('storage.store');
        Route::delete('/storage', [\App\Http\Controllers\StorageController::class, 'destroy'])->name('storage.destroy');
        Route::get('/storage/{path}', [\App\Http\Controllers\StorageController::class, 'show'])->name('storage.show');

        Route::middleware('permission:view-gallery')->group(function () {
            Route::get('gallery', [\App\Http\Controllers\GalleryController::class, 'index'])->name('gallery.index');
            Route::get('gallery/file/{id}', [\App\Http\Controllers\GalleryController::class, 'file'])->name('gallery.file');
        });

        Route::middleware('permission:upload-files')->group(function () {
            Route::post('gallery', [\App\Http\Controllers\GalleryController::class, 'store'])->name('gallery.store');
        });

        Route::middleware('permission:delete-files')->group(function () {
            Route::delete('gallery/{id}', [\App\Http\Controllers\GalleryController::class, 'destroy'])->name('gallery.destroy');
        });

        Route::middleware('permission:manage-folders')->group(function () {
            Route::post('gallery/folder', [\App\Http\Controllers\GalleryController::class, 'createFolder'])->name('gallery.folder.create');
            Route::put('gallery/folder/{id}', [\App\Http\Controllers\GalleryController::class, 'renameFolder'])->name('gallery.folder.rename');
            Route::delete('gallery/folder/{id}', [\App\Http\Controllers\GalleryController::class, 'deleteFolder'])->name('gallery.folder.delete');
        });

        // =====================================================
        // Comment Moderation Routes
        // =====================================================

        // Filter Groups
        Route::post('filter-groups/json', [\App\Http\Controllers\FilterGroupController::class, 'json'])->name('filter-groups.json');
        Route::resource('filter-groups', \App\Http\Controllers\FilterGroupController::class);

        // Filters
        Route::post('filters/json', [\App\Http\Controllers\FilterController::class, 'json'])->name('filters.json');
        Route::post('filters/test-pattern', [\App\Http\Controllers\FilterController::class, 'testPattern'])->name('filters.test-pattern');
        Route::resource('filters', \App\Http\Controllers\FilterController::class);

        // Preset Filters
        Route::get('preset-filters', [\App\Http\Controllers\PresetFilterController::class, 'index'])->name('preset-filters.index');
        Route::get('preset-filters/{id}', [\App\Http\Controllers\PresetFilterController::class, 'show'])->name('preset-filters.show');
        Route::post('preset-filters/{id}/import', [\App\Http\Controllers\PresetFilterController::class, 'import'])->name('preset-filters.import');
        Route::get('api/user-filter-groups', [\App\Http\Controllers\PresetFilterController::class, 'getUserFilterGroups'])->name('api.user-filter-groups');

        // Moderation Logs
        Route::get('moderation-logs', [\App\Http\Controllers\ModerationLogController::class, 'index'])->name('moderation-logs.index');
        Route::post('moderation-logs/json', [\App\Http\Controllers\ModerationLogController::class, 'json'])->name('moderation-logs.json');
        Route::get('moderation-logs/stats', [\App\Http\Controllers\ModerationLogController::class, 'stats'])->name('moderation-logs.stats');
        Route::get('moderation-logs/export', [\App\Http\Controllers\ModerationLogController::class, 'export'])->name('moderation-logs.export');

        // Moderation Dashboard
        Route::get('moderation', [\App\Http\Controllers\ModerationDashboardController::class, 'index'])->name('moderation.index');
        Route::post('moderation/scan/{userPlatformId}', [\App\Http\Controllers\ModerationDashboardController::class, 'scan'])->name('moderation.scan');
        Route::post('moderation/scan-all', [\App\Http\Controllers\ModerationDashboardController::class, 'scanAll'])->name('moderation.scan-all');
        Route::get('moderation/quota-stats', [\App\Http\Controllers\ModerationDashboardController::class, 'quotaStats'])->name('moderation.quota-stats');

        // Review Queue (Pending Moderations)
        Route::get('review-queue', [\App\Http\Controllers\PendingModerationController::class, 'index'])->name('review-queue.index');
        Route::post('review-queue/json', [\App\Http\Controllers\PendingModerationController::class, 'json'])->name('review-queue.json');
        Route::get('review-queue/stats', [\App\Http\Controllers\PendingModerationController::class, 'stats'])->name('review-queue.stats');
        Route::post('review-queue/approve', [\App\Http\Controllers\PendingModerationController::class, 'approve'])->name('review-queue.approve');
        Route::post('review-queue/dismiss', [\App\Http\Controllers\PendingModerationController::class, 'dismiss'])->name('review-queue.dismiss');
        Route::post('review-queue/delete', [\App\Http\Controllers\PendingModerationController::class, 'executeDelete'])->name('review-queue.delete');
        Route::post('review-queue/approve-all', [\App\Http\Controllers\PendingModerationController::class, 'approveAll'])->name('review-queue.approve-all');
        Route::post('review-queue/dismiss-all', [\App\Http\Controllers\PendingModerationController::class, 'dismissAll'])->name('review-queue.dismiss-all');

        // Connected Accounts
        Route::get('connected-accounts', [\App\Http\Controllers\ConnectedAccountController::class, 'index'])->name('connected-accounts.index');
        Route::put('connected-accounts/{id}', [\App\Http\Controllers\ConnectedAccountController::class, 'update'])->name('connected-accounts.update');
        Route::delete('connected-accounts/{id}', [\App\Http\Controllers\ConnectedAccountController::class, 'destroy'])->name('connected-accounts.destroy');
        Route::post('connected-accounts/{platformId}/connect', [\App\Http\Controllers\ConnectedAccountController::class, 'connect'])->name('connected-accounts.connect');
        Route::post('connected-accounts/{id}/scan', [\App\Http\Controllers\ConnectedAccountController::class, 'scan'])->name('connected-accounts.scan');

        // Subscription Plans (placeholder - implement with Stripe later)
        Route::get('subscription/plans', function () {
            $plans = \App\Models\Plan::active()->orderBy('sort_order')->get();

            return \Inertia\Inertia::render('Subscription/Plans', [
                'plans' => $plans,
            ]);
        })->name('subscription.plans');

        Route::middleware('role:admin')->group(function () {
            Route::post('/menus/update-order', [\App\Http\Controllers\MenuController::class, 'updateOrder'])->name('menus.updateOrder');
            Route::get('menus/manage', [\App\Http\Controllers\MenuController::class, 'manage'])->name('menus.manage');
            Route::get('menus/create', [\App\Http\Controllers\MenuController::class, 'create'])->name('menus.create');
            Route::get('menus/{menu}/edit', [\App\Http\Controllers\MenuController::class, 'edit'])->name('menus.edit');
            Route::post('menus', [\App\Http\Controllers\MenuController::class, 'store'])->name('menus.store');
            Route::put('menus/{menu}', [\App\Http\Controllers\MenuController::class, 'update'])->name('menus.update');
            Route::delete('menus/{menu}', [\App\Http\Controllers\MenuController::class, 'destroy'])->name('menus.destroy');
        });
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Admin/Dashboard');
        })->name('dashboard');
        Route::get('/settings', function () {
            return Inertia::render('Admin/Settings');
        })->name('settings')->middleware('permission:manage-settings');
    });

    Route::post('logout', [SocialAuthController::class, 'logout'])->name('logout');

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
