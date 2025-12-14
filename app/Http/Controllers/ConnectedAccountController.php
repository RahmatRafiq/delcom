<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Models\UserPlatform;
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
                    $connections[$method] = [
                        'id' => $userPlatform->id,
                        'platform_username' => $userPlatform->platform_username,
                        'is_active' => $userPlatform->is_active,
                        'auto_moderation_enabled' => $userPlatform->auto_moderation_enabled,
                        'scan_frequency_minutes' => $userPlatform->scan_frequency_minutes,
                        'last_scanned_at' => $userPlatform->last_scanned_at?->toDateTimeString(),
                        'token_expires_at' => $userPlatform->token_expires_at?->toDateTimeString(),
                        'is_token_expired' => $userPlatform->isTokenExpired(),
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
        ]);
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
            'scan_frequency_minutes' => 'integer|min:5|max:1440',
        ]);

        $userPlatform->update($request->only([
            'is_active',
            'auto_moderation_enabled',
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
