<?php

namespace App\Http\Controllers;

use App\Jobs\ScanYouTubeCommentsJob;
use App\Models\ModerationLog;
use App\Models\Platform;
use App\Models\UserPlatform;
use App\Services\YouTubeRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ConnectedAccountController extends Controller
{
    /**
     * Display connected accounts page.
     */
    public function index()
    {
        $user = Auth::user();

        $platforms = Platform::active()->with('connectionMethods')->get();
        $userPlatforms = UserPlatform::where('user_id', $user->id)
            ->with('platform')
            ->get()
            ->keyBy(fn ($up) => $up->platform_id.'_'.$up->connection_method);

        // Merge platform data with user connection status and available methods
        $platformsWithStatus = $platforms->map(function ($platform) use ($userPlatforms, $user) {
            // Get available connection methods for this platform
            $connectionMethods = $platform->connectionMethods->map(function ($cm) use ($platform, $user) {
                // Check if user's plan allows this connection method
                $canAccess = $user->canAccessPlatform($platform->id, $cm->connection_method);

                return [
                    'method' => $cm->connection_method,
                    'requires_business_account' => $cm->requires_business_account,
                    'requires_paid_api' => $cm->requires_paid_api,
                    'notes' => $cm->notes,
                    'is_active' => $cm->is_active,
                    'can_access' => $canAccess,
                ];
            });

            // Check connections per method
            $connections = [];
            foreach (['api', 'extension'] as $method) {
                $key = $platform->id.'_'.$method;
                $userPlatform = $userPlatforms->get($key);
                if ($userPlatform) {
                    // Get today's stats for this connection
                    $todayDeleted = ModerationLog::where('user_platform_id', $userPlatform->id)
                        ->whereDate('processed_at', today())
                        ->where('action_taken', 'deleted')
                        ->count();
                    $todayFailed = ModerationLog::where('user_platform_id', $userPlatform->id)
                        ->whereDate('processed_at', today())
                        ->where('action_taken', 'failed')
                        ->count();

                    $connections[$method] = [
                        'id' => $userPlatform->id,
                        'platform_username' => $userPlatform->platform_username,
                        'is_active' => $userPlatform->is_active,
                        'auto_moderation_enabled' => $userPlatform->auto_moderation_enabled,
                        'auto_delete_enabled' => $userPlatform->auto_delete_enabled,
                        'scan_mode' => $userPlatform->scan_mode,
                        'scan_frequency_minutes' => $userPlatform->scan_frequency_minutes,
                        'last_scanned_at' => $userPlatform->last_scanned_at?->toDateTimeString(),
                        'last_scanned_ago' => $userPlatform->last_scanned_at?->diffForHumans(),
                        'token_expires_at' => $userPlatform->token_expires_at?->toDateTimeString(),
                        'is_token_expired' => $userPlatform->isTokenExpired(),
                        'today_deleted' => $todayDeleted,
                        'today_failed' => $todayFailed,
                    ];
                }
            }

            return [
                'id' => $platform->id,
                'name' => $platform->name,
                'display_name' => $platform->display_name,
                'tier' => $platform->tier,
                'connection_methods' => $connectionMethods,
                'connections' => $connections,
                'is_connected' => count($connections) > 0,
            ];
        });

        // Get user's subscription info
        $currentPlan = $user->getCurrentPlan();
        $usageStats = $user->getUsageStats();

        $quotaStats = $user->hasRole('admin') ? (new YouTubeRateLimiter)->getQuotaStats() : null;

        return Inertia::render('ConnectedAccounts/Index', [
            'platforms' => $platformsWithStatus,
            'subscription' => [
                'plan' => $currentPlan ? [
                    'id' => $currentPlan->id,
                    'slug' => $currentPlan->slug,
                    'name' => $currentPlan->name,
                    'monthly_action_limit' => $currentPlan->monthly_action_limit,
                    'max_platforms' => $currentPlan->max_platforms,
                ] : null,
                'usage' => $usageStats,
            ],
            'quotaStats' => $quotaStats,
        ]);
    }

    /**
     * Trigger a scan for a specific platform connection.
     */
    public function scan(Request $request, int $id)
    {
        $user = Auth::user();

        $userPlatform = UserPlatform::where('id', $id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $userPlatform) {
            return back()->with('error', 'Platform not found or not connected.');
        }

        // Check rate limits
        $rateLimiter = new YouTubeRateLimiter;
        if ($rateLimiter->isRateLimited($user)) {
            return back()->with('error', 'Too many requests. Please wait a moment.');
        }

        if (! $rateLimiter->hasQuotaFor('delete_comment', 10)) {
            return back()->with('error', 'Daily API quota exhausted. Try again tomorrow.');
        }

        // Check user's plan limits (daily and monthly)
        if (! $user->canPerformAction()) {
            $reason = $user->getActionBlockedReason();
            if ($reason === 'daily_limit_reached') {
                return back()->with('error', 'Daily action limit reached. Try again tomorrow or upgrade your plan.');
            }

            return back()->with('error', 'Monthly action limit reached. Upgrade your plan.');
        }

        // Dispatch the scan job
        $maxVideos = $request->input('max_videos', 10);
        $maxComments = $request->input('max_comments', 100);

        ScanYouTubeCommentsJob::dispatch($userPlatform, $maxVideos, $maxComments);

        return back()->with('success', 'Scan started! Results will appear shortly.');
    }

    /**
     * Update connection settings (auto moderation, scan frequency).
     */
    public function update(Request $request, $id)
    {
        $userPlatform = UserPlatform::where('user_id', Auth::id())
            ->findOrFail($id);

        $request->validate([
            'is_active' => 'boolean',
            'auto_moderation_enabled' => 'boolean',
            'auto_delete_enabled' => 'boolean',
            'scan_mode' => 'in:full,incremental,manual',
            'scan_frequency_minutes' => 'integer|min:5|max:1440',
        ]);

        $userPlatform->update($request->only([
            'is_active',
            'auto_moderation_enabled',
            'auto_delete_enabled',
            'scan_mode',
            'scan_frequency_minutes',
        ]));

        return redirect()->back()
            ->with('success', 'Connection settings updated.');
    }

    /**
     * Disconnect a platform.
     */
    public function destroy($id)
    {
        $userPlatform = UserPlatform::where('user_id', Auth::id())
            ->findOrFail($id);

        $platformName = $userPlatform->platform->display_name;
        $userPlatform->delete();

        return redirect()->back()
            ->with('success', "{$platformName} disconnected successfully.");
    }

    /**
     * Initiate OAuth connection for API-tier platforms.
     * Used for re-authentication or connecting additional platforms.
     * Note: YouTube is automatically connected during Google login.
     */
    public function connect(Request $request, $platformId)
    {
        $platform = Platform::active()->findOrFail($platformId);
        $connectionMethod = $request->input('connection_method', 'api');
        $user = Auth::user();

        // Check if user's plan allows this platform and connection method
        if (! $user->canAccessPlatform($platform->id, $connectionMethod)) {
            return redirect()->back()
                ->with('error', "Your current plan doesn't include {$platform->display_name} via {$connectionMethod}. Please upgrade your plan.");
        }

        if ($connectionMethod === 'extension') {
            // Extension connection - redirect to extension installation/setup page
            return redirect()->back()
                ->with('info', "Please use the browser extension to connect {$platform->display_name}.");
        }

        // API connection via OAuth
        if ($platform->name === 'youtube') {
            return redirect()->route('auth.redirect', ['provider' => 'google']);
        }

        // Instagram uses Meta/Facebook OAuth
        if ($platform->name === 'instagram') {
            return redirect()->route('auth.redirect', ['provider' => 'instagram']);
        }

        // Twitter/X OAuth
        if ($platform->name === 'twitter') {
            return redirect()->route('auth.redirect', ['provider' => 'twitter']);
        }

        // Threads uses Meta OAuth (same as Instagram)
        if ($platform->name === 'threads') {
            return redirect()->route('auth.redirect', ['provider' => 'threads']);
        }

        // Other platforms - coming soon
        return redirect()->back()
            ->with('info', "{$platform->display_name} integration coming soon.");
    }
}
