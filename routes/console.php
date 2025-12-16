<?php

use App\Jobs\ScanYouTubeCommentsJob;
use App\Models\UserPlatform;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $platforms = UserPlatform::with(['user', 'platform'])
        ->needsScanning()
        ->whereHas('platform', fn ($q) => $q->where('name', 'youtube'))
        ->get();

    foreach ($platforms as $userPlatform) {
        if ($userPlatform->user->canPerformAction()) {
            ScanYouTubeCommentsJob::dispatch($userPlatform);
        }
    }
})->everyFiveMinutes()->name('auto-scan-youtube-comments');
