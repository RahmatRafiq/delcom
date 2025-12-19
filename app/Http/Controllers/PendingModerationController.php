<?php

namespace App\Http\Controllers;

use App\Helpers\DataTable;
use App\Models\ModerationLog;
use App\Models\PendingModeration;
use App\Models\UsageRecord;
use App\Models\UserPlatform;
use App\Services\PlatformServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PendingModerationController extends Controller
{
    /**
     * Display the review queue page.
     */
    public function index()
    {
        $user = Auth::user();

        // Get pending count by status
        $statusCounts = PendingModeration::where('user_id', $user->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Get connected platforms for filter
        $platforms = UserPlatform::with('platform:id,name,display_name')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->map(fn ($up) => [
                'id' => $up->id,
                'name' => $up->platform->display_name,
            ]);

        return Inertia::render('ReviewQueue/Index', [
            'statusCounts' => $statusCounts,
            'platforms' => $platforms,
        ]);
    }

    /**
     * Get pending moderations as JSON for DataTable.
     */
    public function json(Request $request)
    {
        $user = Auth::user();
        $status = $request->input('status', 'pending');
        $platformId = $request->input('platform_id');
        $search = $request->input('search.value', '');

        $query = PendingModeration::query()
            ->where('user_id', $user->id)
            ->with(['userPlatform.platform:id,name,display_name', 'matchedFilter:id,pattern,type']);

        // Filter by status
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Filter by platform
        if ($platformId) {
            $query->where('user_platform_id', $platformId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('comment_text', 'like', "%{$search}%")
                    ->orWhere('commenter_username', 'like', "%{$search}%")
                    ->orWhere('matched_pattern', 'like', "%{$search}%")
                    ->orWhere('content_title', 'like', "%{$search}%");
            });
        }

        $columns = ['id', 'commenter_username', 'comment_text', 'matched_pattern', 'status', 'detected_at'];

        if ($request->filled('order')) {
            $orderColumn = $columns[$request->order[0]['column']] ?? 'detected_at';
            $query->orderBy($orderColumn, $request->order[0]['dir']);
        } else {
            $query->orderBy('detected_at', 'desc');
        }

        $recordsTotalCallback = null;
        if ($search || $platformId || ($status && $status !== 'pending')) {
            $recordsTotalCallback = fn () => PendingModeration::where('user_id', $user->id)->where('status', 'pending')->count();
        }

        $data = DataTable::paginate($query, $request, $recordsTotalCallback);

        $data['data'] = collect($data['data'])->map(function ($item) {
            return [
                'id' => $item->id,
                'platform_name' => $item->userPlatform?->platform?->display_name ?? 'Unknown',
                'platform_icon' => $item->userPlatform?->platform?->name ?? 'unknown',
                'content_id' => $item->content_id,
                'content_type' => $item->content_type,
                'content_title' => $item->content_title,
                'commenter_username' => $item->commenter_username,
                'commenter_profile_url' => $item->commenter_profile_url,
                'comment_text' => $item->comment_text,
                'matched_pattern' => $item->matched_pattern,
                'matched_filter_type' => $item->matchedFilter?->type,
                'confidence_score' => $item->confidence_score,
                'status' => $item->status,
                'detected_at' => $item->detected_at?->toDateTimeString(),
                'detected_ago' => $item->detected_at?->diffForHumans(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Approve selected items for deletion.
     */
    public function approve(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:pending_moderations,id',
        ]);

        $user = Auth::user();
        $ids = $request->input('ids');

        $updated = PendingModeration::where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->where('status', PendingModeration::STATUS_PENDING)
            ->update([
                'status' => PendingModeration::STATUS_APPROVED,
                'reviewed_at' => now(),
            ]);

        return back()->with('success', "{$updated} item(s) approved for deletion.");
    }

    /**
     * Dismiss selected items (not spam).
     */
    public function dismiss(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:pending_moderations,id',
        ]);

        $user = Auth::user();
        $ids = $request->input('ids');

        $updated = PendingModeration::where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->where('status', PendingModeration::STATUS_PENDING)
            ->update([
                'status' => PendingModeration::STATUS_DISMISSED,
                'reviewed_at' => now(),
            ]);

        return back()->with('success', "{$updated} item(s) dismissed.");
    }

    /**
     * Execute deletion for approved items.
     */
    public function executeDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:pending_moderations,id',
        ]);

        $user = Auth::user();
        $ids = $request->input('ids');

        // Check user quota
        if (! $user->canPerformAction()) {
            return back()->with('error', 'Action limit reached. Upgrade your plan or wait until tomorrow.');
        }

        // Get approved items grouped by platform
        $items = PendingModeration::where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->whereIn('status', [PendingModeration::STATUS_PENDING, PendingModeration::STATUS_APPROVED])
            ->with('userPlatform')
            ->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'No actionable items found.');
        }

        $deleted = 0;
        $failed = 0;

        $grouped = $items->groupBy('user_platform_id');

        foreach ($grouped as $userPlatformId => $platformItems) {
            $userPlatform = $platformItems->first()->userPlatform;

            if (! $userPlatform) {
                continue;
            }

            $platformName = $userPlatform->platform->name;
            if (! PlatformServiceFactory::supports($platformName)) {
                Log::warning('PendingModeration: Platform not supported', ['platform' => $platformName]);

                continue;
            }

            $service = PlatformServiceFactory::make($userPlatform);

            foreach ($platformItems as $item) {
                if (! $user->refresh()->canPerformAction()) {
                    Log::warning('PendingModeration: Quota exhausted mid-deletion', [
                        'user_id' => $user->id,
                        'deleted' => $deleted,
                        'remaining' => $items->count() - $deleted - $failed,
                    ]);
                    break 2;
                }

                $result = $service->deleteComment($item->platform_comment_id);

                DB::transaction(function () use ($item, $result, $user, &$deleted, &$failed) {
                    if ($result['success']) {
                        $item->markDeleted();
                        $deleted++;

                        UsageRecord::recordAction($user->id, 'comment_moderated');

                        ModerationLog::create([
                            'user_id' => $user->id,
                            'user_platform_id' => $item->user_platform_id,
                            'platform_comment_id' => $item->platform_comment_id,
                            'content_id' => $item->content_id,
                            'content_type' => $item->content_type,
                            'commenter_username' => $item->commenter_username,
                            'commenter_id' => $item->commenter_id,
                            'comment_text' => $item->comment_text,
                            'matched_filter_id' => $item->matched_filter_id,
                            'matched_pattern' => $item->matched_pattern,
                            'action_taken' => ModerationLog::ACTION_DELETED,
                            'action_source' => ModerationLog::SOURCE_MANUAL,
                            'processed_at' => now(),
                        ]);
                    } else {
                        $item->markFailed($result['error'] ?? 'Unknown error');
                        $failed++;
                    }
                });
            }
        }

        $message = "Deleted {$deleted} comment(s).";
        if ($failed > 0) {
            $message .= " {$failed} failed.";
        }

        return back()->with($failed > 0 ? 'warning' : 'success', $message);
    }

    /**
     * Get summary stats for the review queue.
     */
    public function stats()
    {
        $user = Auth::user();

        $stats = [
            'pending' => PendingModeration::where('user_id', $user->id)
                ->where('status', PendingModeration::STATUS_PENDING)
                ->count(),
            'approved' => PendingModeration::where('user_id', $user->id)
                ->where('status', PendingModeration::STATUS_APPROVED)
                ->count(),
            'today_detected' => PendingModeration::where('user_id', $user->id)
                ->whereDate('detected_at', today())
                ->count(),
            'today_actioned' => PendingModeration::where('user_id', $user->id)
                ->whereDate('actioned_at', today())
                ->whereIn('status', [PendingModeration::STATUS_DELETED, PendingModeration::STATUS_DISMISSED])
                ->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Bulk approve all pending items.
     */
    public function approveAll(Request $request)
    {
        $user = Auth::user();
        $platformId = $request->input('platform_id');

        $query = PendingModeration::where('user_id', $user->id)
            ->where('status', PendingModeration::STATUS_PENDING);

        if ($platformId) {
            $query->where('user_platform_id', $platformId);
        }

        $updated = $query->update([
            'status' => PendingModeration::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', "{$updated} item(s) approved for deletion.");
    }

    /**
     * Bulk dismiss all pending items.
     */
    public function dismissAll(Request $request)
    {
        $user = Auth::user();
        $platformId = $request->input('platform_id');

        $query = PendingModeration::where('user_id', $user->id)
            ->where('status', PendingModeration::STATUS_PENDING);

        if ($platformId) {
            $query->where('user_platform_id', $platformId);
        }

        $updated = $query->update([
            'status' => PendingModeration::STATUS_DISMISSED,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', "{$updated} item(s) dismissed.");
    }
}
