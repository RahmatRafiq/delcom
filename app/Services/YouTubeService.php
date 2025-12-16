<?php

namespace App\Services;

use App\Models\UserPlatform;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    private const API_BASE = 'https://www.googleapis.com/youtube/v3';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private UserPlatform $userPlatform;

    private ?string $accessToken = null;

    private YouTubeRateLimiter $rateLimiter;

    private ?array $cachedChannel = null;

    public function __construct(UserPlatform $userPlatform)
    {
        $this->userPlatform = $userPlatform;
        $this->rateLimiter = new YouTubeRateLimiter;
    }

    /**
     * Create instance from UserPlatform.
     */
    public static function for(UserPlatform $userPlatform): self
    {
        return new self($userPlatform);
    }

    /**
     * Get the rate limiter instance.
     */
    public function getRateLimiter(): YouTubeRateLimiter
    {
        return $this->rateLimiter;
    }

    /**
     * Check if we can make an API call (rate limit + quota check).
     */
    private function canMakeRequest(string $operation = 'list_videos'): bool
    {
        $user = $this->userPlatform->user;

        // Check user burst rate limit
        if ($this->rateLimiter->isRateLimited($user)) {
            Log::warning('YouTubeService: User rate limited', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        // Check global quota
        if (! $this->rateLimiter->hasQuotaFor($operation)) {
            Log::warning('YouTubeService: Daily quota exhausted', [
                'operation' => $operation,
                'remaining' => $this->rateLimiter->getRemainingDailyQuota(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Track API usage after a successful call.
     */
    private function trackUsage(string $operation, int $count = 1): void
    {
        $user = $this->userPlatform->user;

        // Increment burst rate counter
        $this->rateLimiter->incrementRequestCount($user);

        // Track quota usage
        $this->rateLimiter->trackQuotaUsage($operation, $count);
    }

    /**
     * Get the HTTP client with authorization.
     */
    private function client(): PendingRequest
    {
        $this->ensureValidToken();

        return Http::withToken($this->accessToken)
            ->timeout(30)
            ->retry(2, 100);
    }

    /**
     * Ensure we have a valid access token.
     */
    private function ensureValidToken(): void
    {
        if ($this->accessToken) {
            return;
        }

        // Check if token is expired or about to expire (within 5 minutes)
        if ($this->userPlatform->token_expires_at &&
            $this->userPlatform->token_expires_at->subMinutes(5)->isPast()) {
            $this->refreshToken();
        }

        $this->accessToken = $this->userPlatform->access_token;
    }

    /**
     * Refresh the OAuth access token.
     */
    public function refreshToken(): bool
    {
        $refreshToken = $this->userPlatform->refresh_token;

        if (! $refreshToken) {
            Log::error('YouTubeService: No refresh token available', [
                'user_platform_id' => $this->userPlatform->id,
            ]);

            return false;
        }

        try {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->failed()) {
                Log::error('YouTubeService: Token refresh failed', [
                    'user_platform_id' => $this->userPlatform->id,
                    'error' => $response->json(),
                ]);

                return false;
            }

            $data = $response->json();

            $this->userPlatform->update([
                'access_token' => $data['access_token'],
                'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            ]);

            $this->accessToken = $data['access_token'];

            Log::info('YouTubeService: Token refreshed successfully', [
                'user_platform_id' => $this->userPlatform->id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('YouTubeService: Token refresh exception', [
                'user_platform_id' => $this->userPlatform->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getChannel(): ?array
    {
        if ($this->cachedChannel !== null) {
            return $this->cachedChannel;
        }

        if (! $this->canMakeRequest('list_videos')) {
            return null;
        }

        $response = $this->client()->get(self::API_BASE.'/channels', [
            'part' => 'snippet,contentDetails,statistics',
            'id' => $this->userPlatform->platform_channel_id,
        ]);

        $this->trackUsage('list_videos');

        if ($response->failed()) {
            Log::error('YouTubeService: Failed to get channel', [
                'error' => $response->json(),
            ]);

            return null;
        }

        $this->cachedChannel = $response->json()['items'][0] ?? null;

        return $this->cachedChannel;
    }

    /**
     * Get videos from the channel.
     */
    public function getChannelVideos(int $maxResults = 50, ?string $pageToken = null): array
    {
        if (! $this->canMakeRequest('list_videos')) {
            return ['items' => [], 'nextPageToken' => null, 'error' => 'rate_limited'];
        }

        // First, get the uploads playlist ID
        $channel = $this->getChannel();
        if (! $channel) {
            return ['items' => [], 'nextPageToken' => null];
        }

        $uploadsPlaylistId = $channel['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        if (! $uploadsPlaylistId) {
            return ['items' => [], 'nextPageToken' => null];
        }

        // Get videos from uploads playlist
        $params = [
            'part' => 'snippet,contentDetails',
            'playlistId' => $uploadsPlaylistId,
            'maxResults' => min($maxResults, 50),
        ];

        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $response = $this->client()->get(self::API_BASE.'/playlistItems', $params);

        $this->trackUsage('list_videos');

        if ($response->failed()) {
            Log::error('YouTubeService: Failed to get channel videos', [
                'error' => $response->json(),
            ]);

            return ['items' => [], 'nextPageToken' => null];
        }

        $data = $response->json();

        return [
            'items' => $data['items'] ?? [],
            'nextPageToken' => $data['nextPageToken'] ?? null,
            'totalResults' => $data['pageInfo']['totalResults'] ?? 0,
        ];
    }

    /**
     * Get comments for a specific video.
     */
    public function getVideoComments(string $videoId, int $maxResults = 100, ?string $pageToken = null): array
    {
        if (! $this->canMakeRequest('list_comments')) {
            return ['items' => [], 'nextPageToken' => null, 'error' => 'rate_limited'];
        }

        $params = [
            'part' => 'snippet',
            'videoId' => $videoId,
            'maxResults' => min($maxResults, 100),
            'order' => 'time', // Get newest first
            'textFormat' => 'plainText',
        ];

        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $response = $this->client()->get(self::API_BASE.'/commentThreads', $params);

        $this->trackUsage('list_comments');

        if ($response->failed()) {
            $error = $response->json();

            // Check if comments are disabled
            if (isset($error['error']['errors'][0]['reason']) &&
                $error['error']['errors'][0]['reason'] === 'commentsDisabled') {
                Log::info('YouTubeService: Comments disabled for video', ['video_id' => $videoId]);

                return ['items' => [], 'nextPageToken' => null, 'commentsDisabled' => true];
            }

            Log::error('YouTubeService: Failed to get video comments', [
                'video_id' => $videoId,
                'error' => $error,
            ]);

            return ['items' => [], 'nextPageToken' => null];
        }

        $data = $response->json();

        // Transform to simpler format
        $comments = [];
        foreach ($data['items'] ?? [] as $item) {
            $topComment = $item['snippet']['topLevelComment'];
            $comments[] = [
                'id' => $topComment['id'],
                'videoId' => $videoId,
                'authorDisplayName' => $topComment['snippet']['authorDisplayName'],
                'authorChannelId' => $topComment['snippet']['authorChannelId']['value'] ?? null,
                'textDisplay' => $topComment['snippet']['textDisplay'],
                'textOriginal' => $topComment['snippet']['textOriginal'],
                'publishedAt' => $topComment['snippet']['publishedAt'],
                'updatedAt' => $topComment['snippet']['updatedAt'],
                'likeCount' => $topComment['snippet']['likeCount'] ?? 0,
                'totalReplyCount' => $item['snippet']['totalReplyCount'] ?? 0,
            ];
        }

        return [
            'items' => $comments,
            'nextPageToken' => $data['nextPageToken'] ?? null,
            'totalResults' => $data['pageInfo']['totalResults'] ?? 0,
        ];
    }

    /**
     * Delete a comment.
     *
     * Note: Can only delete comments on videos owned by the authenticated user.
     * Uses setModerationStatus with 'rejected' which effectively removes the comment.
     */
    public function deleteComment(string $commentId): array
    {
        // YouTube API delete requires query parameter, not body
        // Using setModerationStatus with 'rejected' is more reliable
        return $this->setModerationStatus($commentId, 'rejected');
    }

    /**
     * Set comment moderation status (held for review or published).
     * Alternative to delete - hides comment instead.
     */
    public function setModerationStatus(string $commentId, string $status = 'rejected'): array
    {
        if (! $this->canMakeRequest('set_moderation_status')) {
            return ['success' => false, 'error' => 'Rate limit exceeded or quota exhausted'];
        }

        // Valid statuses: heldForReview, published, rejected
        // Note: 'rejected' effectively hides/removes the comment
        $this->ensureValidToken();

        $response = Http::withToken($this->accessToken)
            ->asForm()
            ->post(self::API_BASE.'/comments/setModerationStatus', [
                'id' => $commentId,
                'moderationStatus' => $status,
            ]);

        $this->trackUsage('set_moderation_status');

        if ($response->successful() || $response->status() === 204) {
            Log::info('YouTubeService: Comment moderation status set', [
                'comment_id' => $commentId,
                'status' => $status,
            ]);

            return ['success' => true];
        }

        $error = $response->json();
        Log::error('YouTubeService: Failed to set moderation status', [
            'comment_id' => $commentId,
            'status' => $status,
            'error' => $error,
        ]);

        return [
            'success' => false,
            'error' => $error['error']['message'] ?? 'Unknown error',
            'reason' => $error['error']['errors'][0]['reason'] ?? 'unknown',
        ];
    }

    public function banUser(string $channelIdToBan): array
    {
        if (! $this->canMakeRequest('set_moderation_status')) {
            return ['success' => false, 'error' => 'Rate limit exceeded or quota exhausted'];
        }

        $response = $this->client()->post(self::API_BASE.'/comments/markAsSpam', [
            'id' => $channelIdToBan,
        ]);

        $this->trackUsage('set_moderation_status');

        if ($response->successful()) {
            return ['success' => true];
        }

        return [
            'success' => false,
            'error' => $response->json()['error']['message'] ?? 'Unknown error',
        ];
    }

    public function getCommentReplies(string $parentId, int $maxResults = 100): array
    {
        if (! $this->canMakeRequest('list_comments')) {
            return ['items' => [], 'error' => 'rate_limited'];
        }

        $response = $this->client()->get(self::API_BASE.'/comments', [
            'part' => 'snippet',
            'parentId' => $parentId,
            'maxResults' => min($maxResults, 100),
            'textFormat' => 'plainText',
        ]);

        $this->trackUsage('list_comments');

        if ($response->failed()) {
            return ['items' => []];
        }

        $data = $response->json();
        $replies = [];

        foreach ($data['items'] ?? [] as $item) {
            $replies[] = [
                'id' => $item['id'],
                'authorDisplayName' => $item['snippet']['authorDisplayName'],
                'authorChannelId' => $item['snippet']['authorChannelId']['value'] ?? null,
                'textDisplay' => $item['snippet']['textDisplay'],
                'textOriginal' => $item['snippet']['textOriginal'],
                'publishedAt' => $item['snippet']['publishedAt'],
            ];
        }

        return ['items' => $replies];
    }

    /**
     * Test API connection.
     */
    public function testConnection(): array
    {
        try {
            $channel = $this->getChannel();

            if ($channel) {
                return [
                    'success' => true,
                    'channel' => [
                        'id' => $channel['id'],
                        'title' => $channel['snippet']['title'],
                        'subscriberCount' => $channel['statistics']['subscriberCount'] ?? 0,
                        'videoCount' => $channel['statistics']['videoCount'] ?? 0,
                    ],
                ];
            }

            return ['success' => false, 'error' => 'Could not retrieve channel'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
