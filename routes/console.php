<?php

use App\Jobs\ScanCommentsJob;
use App\Models\UserPlatform;
use App\Services\PlatformServiceFactory;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $platforms = UserPlatform::with(['user', 'platform'])
        ->needsScanning()
        ->whereHas('platform', fn ($q) => $q->whereIn('name', PlatformServiceFactory::getSupportedPlatforms()))
        ->get();

    foreach ($platforms as $userPlatform) {
        if ($userPlatform->user->canPerformAction()) {
            ScanCommentsJob::dispatch($userPlatform);
        }
    }
})->everyFiveMinutes()->name('auto-scan-platform-comments');
