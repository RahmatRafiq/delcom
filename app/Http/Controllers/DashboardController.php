<?php

namespace App\Http\Controllers;

use App\Models\ModerationLog;
use App\Models\User;
use App\Models\UserPlatform;
use App\Services\Platforms\Youtube\YouTubeRateLimiter;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        $data = [];

        if ($isAdmin) {
            $quota = (new YouTubeRateLimiter)->getQuotaStats();

            $data['adminStats'] = [
                'quota' => $quota,
                'totalUsers' => User::count(),
                'totalConnectedPlatforms' => UserPlatform::where('is_active', true)->count(),
                'todayModerations' => ModerationLog::whereDate('processed_at', today())->count(),
                'todaySuccessful' => ModerationLog::whereDate('processed_at', today())
                    ->whereIn('action_taken', ['deleted', 'hidden', 'reported'])
                    ->count(),
            ];
        }

        return Inertia::render('dashboard', $data);
    }
}
