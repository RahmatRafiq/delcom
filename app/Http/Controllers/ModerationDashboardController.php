<?php

namespace App\Http\Controllers;

use App\Jobs\ScanCommentsJob;
use App\Models\ModerationLog;
use App\Models\PendingModeration;
use App\Models\UserPlatform;
use App\Services\PlatformServiceFactory;
use App\Services\Platforms\Youtube\YouTubeRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ModerationDashboardController extends Controller
{
    /**
     * Show the moderation dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        $rateLimiter = new YouTubeRateLimiter;

        $quotaStats = $user->hasRole('admin') ? $rateLimiter->getQuotaStats() : null;

        // Get user's connected platforms with scan info
        $platforms = UserPlatform::with('platform')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->map(function ($userPlatform) {
                return [
                    'id' => $userPlatform->id,
                    'platform_id' => $userPlatform->platform_id,
                    'platform_name' => $userPlatform->platform->name,
                    'platform_display_name' => $userPlatform->platform->display_name,
                    'username' => $userPlatform->platform_username,
                    'channel_id' => $userPlatform->platform_channel_id,
                    'auto_moderation_enabled' => $userPlatform->auto_moderation_enabled,
                    'scan_frequency_minutes' => $userPlatform->scan_frequency_minutes,
                    'last_scanned_at' => $userPlatform->last_scanned_at?->toIso8601String(),
                    'last_scanned_ago' => $userPlatform->last_scanned_at?->diffForHumans(),
                ];
            });

        // Get today's moderation stats
        $todayStats = [
            'total_scanned' => ModerationLog::where('user_id', $user->id)
                ->whereDate('processed_at', today())
                ->count(),
            'total_deleted' => ModerationLog::where('user_id', $user->id)
                ->whereDate('processed_at', today())
                ->where('action_taken', 'deleted')
                ->count(),
            'total_failed' => ModerationLog::where('user_id', $user->id)
                ->whereDate('processed_at', today())
                ->where('action_taken', 'failed')
                ->count(),
        ];

        // Get pending review queue count
        $pendingCount = PendingModeration::where('user_id', $user->id)
            ->where('status', PendingModeration::STATUS_PENDING)
            ->count();

        // Get recent moderation logs
        $recentLogs = ModerationLog::with(['userPlatform.platform', 'matchedFilter'])
            ->where('user_id', $user->id)
            ->latest('processed_at')
            ->take(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'platform' => $log->userPlatform?->platform?->display_name ?? 'Unknown',
                    'comment_text' => mb_substr($log->comment_text, 0, 100),
                    'commenter_username' => $log->commenter_username,
                    'matched_pattern' => $log->matched_pattern,
                    'action_taken' => $log->action_taken,
                    'processed_at' => $log->processed_at?->toIso8601String(),
                    'processed_ago' => $log->processed_at?->diffForHumans(),
                ];
            });

        return Inertia::render('ModerationDashboard/Index', [
            'quotaStats' => $quotaStats,
            'platforms' => $platforms,
            'todayStats' => $todayStats,
            'recentLogs' => $recentLogs,
            'pendingCount' => $pendingCount,
        ]);
    }

    /**
     * Trigger a scan for a specific platform.
     */
    public function scan(Request $request, int $userPlatformId)
    {
        $user = Auth::user();

        $userPlatform = UserPlatform::where('id', $userPlatformId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $userPlatform) {
            return back()->with('error', 'Platform not found or not connected.');
        }

        // Check rate limits
        $rateLimiter = new YouTubeRateLimiter;
        if ($rateLimiter->isRateLimited($user)) {
            return back()->with('error', 'Rate limit exceeded. Please wait a moment.');
        }

        if (! $rateLimiter->hasQuotaFor('delete_comment', 10)) {
            return back()->with('error', 'Daily quota exhausted. Try again tomorrow.');
        }

        // Check user's plan limits (daily and monthly)
        if (! $user->canPerformAction()) {
            $reason = $user->getActionBlockedReason();
            if ($reason === 'daily_limit_reached') {
                return back()->with('error', 'Daily action limit reached. Try again tomorrow or upgrade your plan.');
            }

            return back()->with('error', 'Monthly action limit reached. Upgrade your plan for more.');
        }

        $maxContents = $request->input('max_contents', 10);
        $maxComments = $request->input('max_comments', 100);

        ScanCommentsJob::dispatch($userPlatform, $maxContents, $maxComments);

        return back()->with('success', 'Scan started! Results will appear in moderation logs shortly.');
    }

    /**
     * Trigger a scan for all connected platforms.
     */
    public function scanAll(Request $request)
    {
        $user = Auth::user();

        // Check rate limits first
        $rateLimiter = new YouTubeRateLimiter;
        if ($rateLimiter->isRateLimited($user)) {
            return back()->with('error', 'Rate limit exceeded. Please wait a moment.');
        }

        $platforms = UserPlatform::with('platform')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        if ($platforms->isEmpty()) {
            return back()->with('error', 'No connected platforms found.');
        }

        $maxContents = $request->input('max_contents', 10);
        $maxComments = $request->input('max_comments', 100);
        $dispatched = 0;

        foreach ($platforms as $userPlatform) {
            if (PlatformServiceFactory::supports($userPlatform->platform->name)) {
                ScanCommentsJob::dispatch($userPlatform, $maxContents, $maxComments);
                $dispatched++;
            }
        }

        if ($dispatched === 0) {
            return back()->with('error', 'No scannable platforms found.');
        }

        return back()->with('success', "Scan started for {$dispatched} platform(s)! Results will appear shortly.");
    }

    /**
     * Get quota stats as JSON (for AJAX refresh).
     */
    public function quotaStats()
    {
        $user = Auth::user();

        if (! $user->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $rateLimiter = new YouTubeRateLimiter;

        return response()->json($rateLimiter->getQuotaStats());
    }
}
