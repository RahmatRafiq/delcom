<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Filter;
use App\Models\ModerationLog;
use App\Models\PendingModeration;
use App\Models\Platform;
use App\Models\UsageRecord;
use App\Models\UserPlatform;
use App\Services\FilterMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * API Controller for Browser Extension
 *
 * Handles communication between browser extension and Delcom backend.
 * Extension sends scraped comments, receives deletion commands.
 */
class ExtensionController extends Controller
{
    /**
     * Verify extension connection and get user settings.
     */
    public function verify(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Get all extension-based connections for this user
        $connections = UserPlatform::where('user_id', $user->id)
            ->where('connection_method', 'extension')
            ->where('is_active', true)
            ->with('platform:id,name,display_name')
            ->get()
            ->map(fn ($up) => [
                'id' => $up->id,
                'platform' => $up->platform->name,
                'platform_display' => $up->platform->display_name,
                'username' => $up->platform_username,
                'auto_moderation' => $up->auto_moderation_enabled,
                'auto_delete' => $up->auto_delete_enabled,
            ]);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'can_moderate' => $user->canPerformAction(),
            ],
            'connections' => $connections,
        ]);
    }

    /**
     * Register/connect a new platform via extension.
     */
    public function connect(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'required|string|in:instagram,twitter,threads,tiktok',
            'username' => 'required|string|max:255',
            'user_id' => 'nullable|string|max:255', // Platform's user ID
        ]);

        $user = Auth::user();
        $platformName = $request->input('platform');

        // Find platform
        $platform = Platform::where('name', $platformName)->first();
        if (! $platform) {
            return response()->json([
                'success' => false,
                'error' => 'Platform not found',
            ], 404);
        }

        // Check if already connected
        $existing = UserPlatform::where('user_id', $user->id)
            ->where('platform_id', $platform->id)
            ->where('connection_method', 'extension')
            ->first();

        if ($existing) {
            // Update existing connection
            $existing->update([
                'platform_username' => $request->input('username'),
                'platform_user_id' => $request->input('user_id'),
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Connection updated',
                'connection_id' => $existing->id,
            ]);
        }

        // Create new connection
        $userPlatform = UserPlatform::create([
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'connection_method' => 'extension',
            'platform_username' => $request->input('username'),
            'platform_user_id' => $request->input('user_id'),
            'is_active' => true,
            'auto_moderation_enabled' => false,
            'auto_delete_enabled' => false,
            'scan_mode' => UserPlatform::SCAN_MODE_MANUAL,
        ]);

        Log::info('ExtensionController: New extension connection', [
            'user_id' => $user->id,
            'platform' => $platformName,
            'username' => $request->input('username'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Connected successfully',
            'connection_id' => $userPlatform->id,
        ]);
    }

    /**
     * Receive comments from extension for scanning.
     */
    public function submitComments(Request $request, FilterMatcher $filterMatcher): JsonResponse
    {
        $request->validate([
            'connection_id' => 'required|integer|exists:user_platforms,id',
            'content_id' => 'required|string|max:255',
            'content_title' => 'nullable|string|max:500',
            'content_url' => 'nullable|url|max:1000',
            'comments' => 'required|array|min:1',
            'comments.*.id' => 'required|string|max:255',
            'comments.*.text' => 'required|string',
            'comments.*.author_username' => 'required|string|max:255',
            'comments.*.author_id' => 'nullable|string|max:255',
            'comments.*.author_profile_url' => 'nullable|url|max:1000',
            'comments.*.created_at' => 'nullable|string',
        ]);

        $user = Auth::user();
        $connectionId = $request->input('connection_id');

        // Verify ownership
        $userPlatform = UserPlatform::where('id', $connectionId)
            ->where('user_id', $user->id)
            ->where('connection_method', 'extension')
            ->with('platform')
            ->first();

        if (! $userPlatform) {
            return response()->json([
                'success' => false,
                'error' => 'Connection not found or not authorized',
            ], 403);
        }

        $platformName = $userPlatform->platform->name;
        $useReviewQueue = $userPlatform->usesReviewQueue();

        // Get user's active filters for this platform
        $filters = $user->getActiveFilters($platformName);

        if ($filters->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No active filters configured',
                'scanned' => count($request->input('comments')),
                'matched' => 0,
                'queued' => 0,
            ]);
        }

        $contentId = $request->input('content_id');
        $contentTitle = $request->input('content_title', 'Unknown');
        $comments = $request->input('comments');

        $scanned = 0;
        $matched = 0;
        $queued = 0;
        $actioned = 0;
        $matchedComments = [];

        foreach ($comments as $comment) {
            $scanned++;

            // Check if comment matches any filter
            $matchedFilter = $filterMatcher->findMatch($comment['text'], $filters);

            if (! $matchedFilter) {
                continue;
            }

            $matched++;

            // Check if already processed
            $existsInQueue = PendingModeration::where('user_platform_id', $userPlatform->id)
                ->where('platform_comment_id', $comment['id'])
                ->exists();

            $existsInLog = ModerationLog::where('user_platform_id', $userPlatform->id)
                ->where('platform_comment_id', $comment['id'])
                ->exists();

            if ($existsInQueue || $existsInLog) {
                continue;
            }

            if ($useReviewQueue) {
                // Add to review queue
                PendingModeration::create([
                    'user_id' => $user->id,
                    'user_platform_id' => $userPlatform->id,
                    'platform_comment_id' => $comment['id'],
                    'content_id' => $contentId,
                    'content_title' => $contentTitle,
                    'commenter_username' => $comment['author_username'],
                    'commenter_id' => $comment['author_id'] ?? null,
                    'commenter_profile_url' => $comment['author_profile_url'] ?? null,
                    'comment_text' => mb_substr($comment['text'], 0, 1000),
                    'matched_filter_id' => $matchedFilter->id,
                    'matched_pattern' => $matchedFilter->pattern,
                    'confidence_score' => 100,
                    'status' => PendingModeration::STATUS_PENDING,
                    'detected_at' => now(),
                ]);

                $queued++;
                $matchedFilter->incrementHitCount();
            } else {
                // Return immediately for extension to delete
                $matchedComments[] = [
                    'comment_id' => $comment['id'],
                    'action' => $matchedFilter->action,
                    'filter_pattern' => $matchedFilter->pattern,
                ];

                $actioned++;
                $matchedFilter->incrementHitCount();
            }
        }

        // Update last scanned timestamp
        $userPlatform->update(['last_scanned_at' => now()]);

        Log::info('ExtensionController: Comments scanned', [
            'user_id' => $user->id,
            'platform' => $platformName,
            'content_id' => $contentId,
            'scanned' => $scanned,
            'matched' => $matched,
            'queued' => $queued,
            'actioned' => $actioned,
        ]);

        return response()->json([
            'success' => true,
            'scanned' => $scanned,
            'matched' => $matched,
            'queued' => $queued,
            'to_delete' => $matchedComments, // For extension to execute
            'use_review_queue' => $useReviewQueue,
        ]);
    }

    /**
     * Get pending deletions for extension to execute.
     */
    public function getPendingDeletions(Request $request): JsonResponse
    {
        $request->validate([
            'connection_id' => 'required|integer|exists:user_platforms,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $connectionId = $request->input('connection_id');
        $limit = $request->input('limit', 20);

        // Verify ownership
        $userPlatform = UserPlatform::where('id', $connectionId)
            ->where('user_id', $user->id)
            ->where('connection_method', 'extension')
            ->first();

        if (! $userPlatform) {
            return response()->json([
                'success' => false,
                'error' => 'Connection not found',
            ], 403);
        }

        // Get approved items waiting for deletion
        $pending = PendingModeration::where('user_platform_id', $connectionId)
            ->where('status', PendingModeration::STATUS_APPROVED)
            ->orderBy('reviewed_at', 'asc')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'comment_id' => $item->platform_comment_id,
                'content_id' => $item->content_id,
                'comment_text' => Str::limit($item->comment_text, 100),
            ]);

        return response()->json([
            'success' => true,
            'pending' => $pending,
            'count' => $pending->count(),
        ]);
    }

    /**
     * Report deletion results from extension.
     */
    public function reportDeletions(Request $request): JsonResponse
    {
        $request->validate([
            'connection_id' => 'required|integer|exists:user_platforms,id',
            'results' => 'required|array',
            'results.*.pending_id' => 'required|integer',
            'results.*.success' => 'required|boolean',
            'results.*.error' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $connectionId = $request->input('connection_id');

        // Verify ownership
        $userPlatform = UserPlatform::where('id', $connectionId)
            ->where('user_id', $user->id)
            ->first();

        if (! $userPlatform) {
            return response()->json([
                'success' => false,
                'error' => 'Connection not found',
            ], 403);
        }

        $results = $request->input('results');
        $deleted = 0;
        $failed = 0;

        foreach ($results as $result) {
            $pending = PendingModeration::where('id', $result['pending_id'])
                ->where('user_platform_id', $connectionId)
                ->first();

            if (! $pending) {
                continue;
            }

            DB::transaction(function () use ($pending, $result, $user, $userPlatform, &$deleted, &$failed) {
                if ($result['success']) {
                    $pending->markDeleted();
                    $deleted++;

                    // Record usage
                    UsageRecord::recordAction($user->id, 'comment_moderated');

                    // Log to moderation_logs
                    ModerationLog::create([
                        'user_id' => $user->id,
                        'user_platform_id' => $userPlatform->id,
                        'platform_comment_id' => $pending->platform_comment_id,
                        'content_id' => $pending->content_id,
                        'commenter_username' => $pending->commenter_username,
                        'commenter_id' => $pending->commenter_id,
                        'comment_text' => $pending->comment_text,
                        'matched_filter_id' => $pending->matched_filter_id,
                        'matched_pattern' => $pending->matched_pattern,
                        'action_taken' => ModerationLog::ACTION_DELETED,
                        'action_source' => ModerationLog::SOURCE_EXTENSION,
                        'processed_at' => now(),
                    ]);
                } else {
                    $pending->markFailed($result['error'] ?? 'Extension deletion failed');
                    $failed++;
                }
            });
        }

        Log::info('ExtensionController: Deletions reported', [
            'user_id' => $user->id,
            'connection_id' => $connectionId,
            'deleted' => $deleted,
            'failed' => $failed,
        ]);

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
            'failed' => $failed,
        ]);
    }

    /**
     * Save ALL scraped comments to review queue (without filtering).
     * For manual review by user.
     */
    public function saveAllComments(Request $request): JsonResponse
    {
        $request->validate([
            'connection_id' => 'required|integer|exists:user_platforms,id',
            'content_id' => 'required|string|max:255',
            'content_title' => 'nullable|string|max:500',
            'content_url' => 'nullable|string|max:1000', // Relaxed - might not be full URL
            'comments' => 'required|array|min:1',
            'comments.*.id' => 'required|string|max:255',
            'comments.*.text' => 'required|string',
            'comments.*.author_username' => 'required|string|max:255',
            'comments.*.author_id' => 'nullable|string|max:255',
            'comments.*.author_profile_url' => 'nullable|string|max:1000', // Relaxed - might be path only
            'comments.*.created_at' => 'nullable|string',
        ]);

        $user = Auth::user();
        $connectionId = $request->input('connection_id');

        // Verify ownership
        $userPlatform = UserPlatform::where('id', $connectionId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with('platform')
            ->first();

        if (! $userPlatform) {
            return response()->json([
                'success' => false,
                'error' => 'Connection not found or not authorized',
            ], 403);
        }

        $contentId = $request->input('content_id');
        $contentTitle = $request->input('content_title', 'Instagram Post');
        $comments = $request->input('comments');

        $saved = 0;
        $skipped = 0;

        foreach ($comments as $comment) {
            // Check if already exists (avoid duplicates)
            $exists = PendingModeration::where('user_platform_id', $userPlatform->id)
                ->where('platform_comment_id', $comment['id'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Also check in moderation logs
            $existsInLog = ModerationLog::where('user_platform_id', $userPlatform->id)
                ->where('platform_comment_id', $comment['id'])
                ->exists();

            if ($existsInLog) {
                $skipped++;
                continue;
            }

            // Save to review queue
            PendingModeration::create([
                'user_id' => $user->id,
                'user_platform_id' => $userPlatform->id,
                'platform_comment_id' => $comment['id'],
                'video_id' => $contentId,
                'video_title' => $contentTitle,
                'commenter_username' => $comment['author_username'],
                'commenter_id' => $comment['author_id'] ?? null,
                'commenter_profile_url' => $comment['author_profile_url'] ?? null,
                'comment_text' => mb_substr($comment['text'], 0, 1000),
                'matched_filter_id' => null, // No filter matching - manual review
                'matched_pattern' => null,
                'confidence_score' => 0,
                'status' => PendingModeration::STATUS_PENDING,
                'detected_at' => now(),
            ]);

            $saved++;
        }

        // Update last scanned timestamp
        $userPlatform->update(['last_scanned_at' => now()]);

        Log::info('ExtensionController: All comments saved to review queue', [
            'user_id' => $user->id,
            'platform' => $userPlatform->platform->name,
            'content_id' => $contentId,
            'total' => count($comments),
            'saved' => $saved,
            'skipped' => $skipped,
        ]);

        return response()->json([
            'success' => true,
            'total' => count($comments),
            'saved' => $saved,
            'skipped' => $skipped,
            'message' => "Saved {$saved} comments to review queue" . ($skipped > 0 ? " ({$skipped} duplicates skipped)" : ''),
        ]);
    }

    /**
     * Get user's filters for extension to use locally.
     */
    public function getFilters(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'required|string|in:instagram,twitter,threads,tiktok,youtube',
        ]);

        $user = Auth::user();
        $platformName = $request->input('platform');

        $filters = $user->getActiveFilters($platformName)
            ->map(fn ($filter) => [
                'id' => $filter->id,
                'pattern' => $filter->pattern,
                'type' => $filter->type,
                'action' => $filter->action,
                'is_regex' => $filter->type === Filter::TYPE_REGEX,
            ]);

        return response()->json([
            'success' => true,
            'filters' => $filters,
            'count' => $filters->count(),
        ]);
    }
}
