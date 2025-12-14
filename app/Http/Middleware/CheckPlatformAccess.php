<?php

namespace App\Http\Middleware;

use App\Models\Platform;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlatformAccess
{
    /**
     * Handle an incoming request.
     * Check if user's plan allows access to the requested platform.
     */
    public function handle(Request $request, Closure $next, ?string $method = null): Response
    {
        $user = $request->user();

        // Get platform ID from route or request
        $platformId = $request->route('platform_id')
            ?? $request->route('platformId')
            ?? $request->input('platform_id');

        if (! $platformId || ! $user) {
            return $next($request);
        }

        $platform = Platform::find($platformId);

        if (! $platform) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Platform not found',
                    'code' => 'PLATFORM_NOT_FOUND',
                ], 404);
            }

            abort(404, 'Platform not found');
        }

        // Determine connection method from request or default to 'api'
        $connectionMethod = $method ?? $request->input('connection_method', 'api');

        if (! $user->canAccessPlatform($platform->id, $connectionMethod)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Your plan does not include access to this platform',
                    'code' => 'PLATFORM_NOT_ALLOWED',
                    'message' => "Your current plan doesn't include {$platform->display_name} access. Please upgrade your plan.",
                    'upgrade_url' => route('subscription.plans'),
                ], 403);
            }

            return redirect()->back()
                ->with('error', "Your current plan doesn't include {$platform->display_name} access. Please upgrade your plan.");
        }

        return $next($request);
    }
}
