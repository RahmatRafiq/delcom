<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCloudQuotaService
{
    private const YOUTUBE_DAILY_LIMIT = 10000;

    private ?string $projectId;

    private ?string $serviceAccountPath;

    public function __construct()
    {
        $this->projectId = config('services.google.project_id');
        $this->serviceAccountPath = config('services.google.service_account_path');
    }

    public function getYouTubeQuotaStats(): array
    {
        if (! $this->projectId) {
            return $this->fallbackStats();
        }

        $cacheKey = 'google_youtube_quota_'.now()->format('Y-m-d-H');

        return Cache::remember($cacheKey, 300, fn () => $this->fetchQuotaUsage());
    }

    private function fetchQuotaUsage(): array
    {
        $accessToken = $this->getAccessToken();

        if (! $accessToken) {
            Log::warning('GoogleCloudQuotaService: No access token, using fallback');

            return $this->fallbackStats();
        }

        try {
            $response = Http::withToken($accessToken)
                ->post('https://monitoring.googleapis.com/v3/projects/'.$this->projectId.'/timeSeries:query', [
                    'query' => "fetch consumer_quota
                        | metric 'serviceruntime.googleapis.com/quota/allocation/usage'
                        | filter resource.service == 'youtube.googleapis.com'
                        | group_by 1d, [value_usage_mean: mean(value.usage)]
                        | within 1d",
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $used = $this->parseUsageFromResponse($data);

                return [
                    'used' => $used,
                    'limit' => self::YOUTUBE_DAILY_LIMIT,
                    'remaining' => max(0, self::YOUTUBE_DAILY_LIMIT - $used),
                    'percentage' => round(($used / self::YOUTUBE_DAILY_LIMIT) * 100, 2),
                    'reset_at' => now()->endOfDay()->toIso8601String(),
                    'can_delete_comments' => (int) floor((self::YOUTUBE_DAILY_LIMIT - $used) / 50),
                    'source' => 'google_api',
                ];
            }

            Log::warning('GoogleCloudQuotaService: API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->fallbackStats();
        } catch (\Exception $e) {
            Log::error('GoogleCloudQuotaService: Exception', ['error' => $e->getMessage()]);

            return $this->fallbackStats();
        }
    }

    private function parseUsageFromResponse(array $data): int
    {
        $timeSeriesData = $data['timeSeriesData'] ?? [];

        foreach ($timeSeriesData as $series) {
            $pointData = $series['pointData'] ?? [];
            foreach ($pointData as $point) {
                $values = $point['values'] ?? [];
                foreach ($values as $value) {
                    if (isset($value['int64Value'])) {
                        return (int) $value['int64Value'];
                    }
                    if (isset($value['doubleValue'])) {
                        return (int) $value['doubleValue'];
                    }
                }
            }
        }

        return 0;
    }

    private function getAccessToken(): ?string
    {
        if ($this->serviceAccountPath && file_exists($this->serviceAccountPath)) {
            return $this->getAccessTokenFromServiceAccount();
        }

        return $this->getAccessTokenFromMetadata();
    }

    private function getAccessTokenFromServiceAccount(): ?string
    {
        try {
            $cacheKey = 'google_sa_token';

            return Cache::remember($cacheKey, 3500, function () {
                $serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);

                $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
                $now = time();
                $claim = $this->base64UrlEncode(json_encode([
                    'iss' => $serviceAccount['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/monitoring.read',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]));

                $signature = '';
                openssl_sign("$header.$claim", $signature, $serviceAccount['private_key'], 'SHA256');
                $jwt = "$header.$claim.".$this->base64UrlEncode($signature);

                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                return $response->successful() ? $response->json()['access_token'] : null;
            });
        } catch (\Exception $e) {
            Log::error('GoogleCloudQuotaService: SA auth failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function getAccessTokenFromMetadata(): ?string
    {
        try {
            $response = Http::timeout(2)
                ->withHeaders(['Metadata-Flavor' => 'Google'])
                ->get('http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token');

            return $response->successful() ? $response->json()['access_token'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function fallbackStats(): array
    {
        $rateLimiter = new YouTubeRateLimiter;

        return array_merge($rateLimiter->getQuotaStats(), ['source' => 'cache_fallback']);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->projectId) &&
               ! empty($this->serviceAccountPath) &&
               file_exists($this->serviceAccountPath);
    }
}
