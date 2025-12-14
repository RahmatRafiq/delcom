<?php

namespace App\Http\Controllers;

use App\Helpers\DataTable;
use App\Models\ModerationLog;
use App\Models\Platform;
use App\Models\UserPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ModerationLogController extends Controller
{
    public function index()
    {
        $userPlatformIds = UserPlatform::where('user_id', Auth::id())->pluck('id');
        $platforms = Platform::where('is_active', true)->get();

        return Inertia::render('ModerationLogs/Index', [
            'platforms' => $platforms,
        ]);
    }

    public function json(Request $request)
    {
        $search = $request->input('search.value', '');
        $platformId = $request->input('platform_id');
        $actionTaken = $request->input('action_taken');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Get user's platform IDs
        $userPlatformIds = UserPlatform::where('user_id', Auth::id())->pluck('id');

        $query = ModerationLog::query()
            ->whereIn('user_platform_id', $userPlatformIds)
            ->with(['userPlatform.platform:id,name,display_name', 'matchedFilter:id,pattern,type']);

        $columns = [
            'id',
            'commenter_username',
            'comment_text',
            'action_taken',
            'action_source',
            'processed_at',
        ];

        // Apply filters
        if ($platformId) {
            $platformUserIds = UserPlatform::where('user_id', Auth::id())
                ->where('platform_id', $platformId)
                ->pluck('id');
            $query->whereIn('user_platform_id', $platformUserIds);
        }

        if ($actionTaken) {
            $query->where('action_taken', $actionTaken);
        }

        if ($dateFrom) {
            $query->whereDate('processed_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('processed_at', '<=', $dateTo);
        }

        $recordsTotalCallback = null;
        if ($search || $platformId || $actionTaken || $dateFrom || $dateTo) {
            $recordsTotalCallback = function () use ($userPlatformIds) {
                return ModerationLog::whereIn('user_platform_id', $userPlatformIds)->count();
            };
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('comment_text', 'like', "%{$search}%")
                    ->orWhere('commenter_username', 'like', "%{$search}%")
                    ->orWhere('matched_pattern', 'like', "%{$search}%");
            });
        }

        if ($request->filled('order')) {
            $orderColumn = $columns[$request->order[0]['column']] ?? 'processed_at';
            $query->orderBy($orderColumn, $request->order[0]['dir']);
        } else {
            $query->orderBy('processed_at', 'desc');
        }

        $data = DataTable::paginate($query, $request, $recordsTotalCallback);

        $data['data'] = collect($data['data'])->map(function ($log) {
            return [
                'id' => $log->id,
                'platform_name' => $log->userPlatform?->platform?->display_name ?? 'Unknown',
                'platform_icon' => $log->userPlatform?->platform?->name ?? 'unknown',
                'commenter_username' => $log->commenter_username,
                'comment_text' => $log->comment_text,
                'matched_pattern' => $log->matched_pattern,
                'matched_filter_type' => $log->matchedFilter?->type,
                'action_taken' => $log->action_taken,
                'action_source' => $log->action_source,
                'failure_reason' => $log->failure_reason,
                'video_id' => $log->video_id,
                'post_id' => $log->post_id,
                'processed_at' => $log->processed_at?->toDateTimeString(),
            ];
        });

        return response()->json($data);
    }

    public function stats()
    {
        $userPlatformIds = UserPlatform::where('user_id', Auth::id())->pluck('id');

        $stats = [
            'total' => ModerationLog::whereIn('user_platform_id', $userPlatformIds)->count(),
            'today' => ModerationLog::whereIn('user_platform_id', $userPlatformIds)
                ->whereDate('processed_at', today())
                ->count(),
            'this_week' => ModerationLog::whereIn('user_platform_id', $userPlatformIds)
                ->whereBetween('processed_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'by_action' => ModerationLog::whereIn('user_platform_id', $userPlatformIds)
                ->selectRaw('action_taken, count(*) as count')
                ->groupBy('action_taken')
                ->pluck('count', 'action_taken'),
            'by_source' => ModerationLog::whereIn('user_platform_id', $userPlatformIds)
                ->selectRaw('action_source, count(*) as count')
                ->groupBy('action_source')
                ->pluck('count', 'action_source'),
            'by_platform' => ModerationLog::whereIn('user_platform_id', $userPlatformIds)
                ->join('user_platforms', 'moderation_logs.user_platform_id', '=', 'user_platforms.id')
                ->join('platforms', 'user_platforms.platform_id', '=', 'platforms.id')
                ->selectRaw('platforms.display_name, count(*) as count')
                ->groupBy('platforms.display_name')
                ->pluck('count', 'display_name'),
        ];

        return response()->json($stats);
    }

    public function export(Request $request)
    {
        $userPlatformIds = UserPlatform::where('user_id', Auth::id())->pluck('id');

        $logs = ModerationLog::whereIn('user_platform_id', $userPlatformIds)
            ->with(['userPlatform.platform:id,name,display_name', 'matchedFilter:id,pattern,type'])
            ->orderBy('processed_at', 'desc')
            ->limit(1000)
            ->get();

        $csv = "ID,Platform,Commenter,Comment,Matched Pattern,Action,Source,Processed At\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%s,%s,\"%s\",\"%s\",%s,%s,%s\n",
                $log->id,
                $log->userPlatform?->platform?->display_name ?? 'Unknown',
                $log->commenter_username ?? 'Unknown',
                str_replace('"', '""', $log->comment_text ?? ''),
                str_replace('"', '""', $log->matched_pattern ?? ''),
                $log->action_taken,
                $log->action_source,
                $log->processed_at?->toDateTimeString() ?? ''
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="moderation_logs_'.now()->format('Y-m-d').'.csv"',
        ]);
    }
}
