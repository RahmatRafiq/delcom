<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimiter
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $limitType = 'default'): Response
    {
        $key = $this->resolveRequestSignature($request, $limitType);
        $limits = $this->getLimits($limitType, $request);

        if ($this->limiter->tooManyAttempts($key, $limits['max_attempts'])) {
            return $this->buildTooManyAttemptsResponse($key, $limits['max_attempts']);
        }

        $this->limiter->hit($key, $limits['decay_seconds']);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $limits['max_attempts'],
            $this->calculateRemainingAttempts($key, $limits['max_attempts'])
        );
    }

    /**
     * Resolve request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request, string $limitType): string
    {
        // Use authenticated user ID if available, otherwise IP
        $identifier = $request->user('api')?->id ?? $request->ip();

        return "api_rate_limit:{$limitType}:{$identifier}";
    }

    /**
     * Get rate limits based on type and user subscription.
     */
    protected function getLimits(string $limitType, Request $request): array
    {
        $user = $request->user('api');
        $planMultiplier = 1;

        // Higher limits for paid plans
        if ($user) {
            $plan = $user->getCurrentPlan();
            $planMultiplier = match ($plan?->slug) {
                'pro' => 2,
                'business' => 5,
                'enterprise' => 10,
                default => 1,
            };
        }

        // Base limits per type
        $baseLimits = match ($limitType) {
            'comments' => [
                'max_attempts' => 30,   // 30 requests
                'decay_seconds' => 60,  // per minute
            ],
            'scan' => [
                'max_attempts' => 10,   // 10 scans
                'decay_seconds' => 60,  // per minute
            ],
            'delete' => [
                'max_attempts' => 100,  // 100 deletions
                'decay_seconds' => 3600, // per hour
            ],
            'auth' => [
                'max_attempts' => 5,    // 5 attempts
                'decay_seconds' => 60,  // per minute (no multiplier)
            ],
            default => [
                'max_attempts' => 60,   // 60 requests
                'decay_seconds' => 60,  // per minute
            ],
        };

        // Apply plan multiplier (except for auth)
        if ($limitType !== 'auth') {
            $baseLimits['max_attempts'] *= $planMultiplier;
        }

        return $baseLimits;
    }

    /**
     * Create a 'too many attempts' response.
     */
    protected function buildTooManyAttemptsResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'success' => false,
            'error' => 'Too many requests',
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => $retryAfter,
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);

        return $response;
    }

    /**
     * Calculate remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return $this->limiter->remaining($key, $maxAttempts);
    }
}
