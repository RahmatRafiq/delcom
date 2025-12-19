<?php

use App\Jobs\ScanCommentsJob;
use App\Models\PendingModeration;
use App\Models\UserPlatform;
use App\Services\PlatformServiceFactory;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Commands
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('scheduler:test', function () {
    $this->info('Scheduler is working correctly!');
    $this->info('Current time: '.now()->toDateTimeString());
    $this->info('Timezone: '.config('app.timezone'));
})->purpose('Test if the scheduler is running');

Artisan::command('moderation:stats', function () {
    $pending = PendingModeration::where('status', 'pending')->count();
    $approved = PendingModeration::where('status', 'approved')->count();
    $dismissed = PendingModeration::where('status', 'dismissed')->count();

    $this->table(
        ['Status', 'Count'],
        [
            ['Pending', $pending],
            ['Approved', $approved],
            ['Dismissed', $dismissed],
        ]
    );
})->purpose('Show moderation queue statistics');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| Laravel 12 uses routes/console.php for scheduling
| Run with: php artisan schedule:work (development)
|           * * * * * php /path/artisan schedule:run >> /dev/null 2>&1 (production cron)
*/

// Auto-scan platforms every 5 minutes
Schedule::call(function () {
    $platforms = UserPlatform::with(['user', 'platform'])
        ->needsScanning()
        ->whereHas('platform', fn ($q) => $q->whereIn('name', PlatformServiceFactory::getSupportedPlatforms()))
        ->get();

    $dispatched = 0;
    foreach ($platforms as $userPlatform) {
        if ($userPlatform->user->canPerformAction()) {
            ScanCommentsJob::dispatch($userPlatform);
            $dispatched++;
        }
    }

    if ($dispatched > 0) {
        Log::info('Auto-scan scheduled', ['platforms' => $dispatched]);
    }
})->everyFiveMinutes()
    ->name('auto-scan-platform-comments')
    ->withoutOverlapping()
    ->onOneServer();

// Clean up old pending moderations (older than 30 days)
Schedule::call(function () {
    $deleted = PendingModeration::where('status', '!=', 'pending')
        ->where('updated_at', '<', now()->subDays(30))
        ->delete();

    if ($deleted > 0) {
        Log::info('Cleaned up old moderations', ['count' => $deleted]);
    }
})->daily()
    ->name('cleanup-old-moderations')
    ->onOneServer();

// Reset daily quota cache at midnight
Schedule::call(function () {
    $yesterday = now()->subDay()->format('Y-m-d');
    Cache::forget("youtube_quota:daily:{$yesterday}");

    Log::info('Daily quota reset');
})->dailyAt('00:05')
    ->name('reset-daily-quota')
    ->onOneServer();

// Refresh expired OAuth tokens
Schedule::call(function () {
    $expiringSoon = UserPlatform::where('is_active', true)
        ->where('token_expires_at', '<', now()->addHours(1))
        ->where('token_expires_at', '>', now())
        ->get();

    foreach ($expiringSoon as $platform) {
        try {
            // Token refresh logic would go here
            // This is platform-specific and would need implementation per platform
            Log::info('Token expiring soon', [
                'platform' => $platform->platform->name,
                'user_id' => $platform->user_id,
                'expires_at' => $platform->token_expires_at,
            ]);
        } catch (\Exception $e) {
            Log::error('Token refresh failed', [
                'platform_id' => $platform->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
})->hourly()
    ->name('refresh-expiring-tokens')
    ->onOneServer();
