<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionLimit
{
    /**
     * Handle an incoming request.
     * Check if user has remaining actions in their subscription plan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->canPerformAction()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Monthly action limit reached',
                    'code' => 'LIMIT_EXCEEDED',
                    'message' => 'You have reached your monthly moderation action limit. Please upgrade your plan for more actions.',
                    'upgrade_url' => route('subscription.plans'),
                ], 429);
            }

            return redirect()->back()
                ->with('error', 'You have reached your monthly action limit. Please upgrade your plan.');
        }

        return $next($request);
    }
}
