<?php

namespace App\Services\Platforms\Youtube;

use App\Contracts\PlatformServiceInterface;
use App\Models\UserContent;
use App\Models\UserPlatform;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeService implements PlatformServiceInterface
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

    public static function for(UserPlatform $userPlatform): self
    {
        return new self($userPlatform);
    }

    public function getPlatformName(): string
    {
        return 'youtube';
    }

    public function getContentType(): string
    {
        return UserContent::TYPE_VIDEO;
    }

    public function isApiConnection(): bool
    {
        return $this->userPlatform->connection_method === 'api';
    }

    public function isExtensionConnection(): bool
    {
        return $this->userPlatform->connection_method === 'extension';
    }

    public function getRateLimiter(): YouTubeRateLimiter
    {
        return $this->rateLimiter;
    }

    private function canMakeRequest(string $operation = 'list_videos'): bool
    {
        $user = $this->userPlatform->user;

        if ($this->rateLimiter->isRateLimited($user)) {
            Log::warning('YouTubeService: User rate limited', ['user_id' => $user->id]);

            return false;
        }

        if (! $this->rateLimiter->hasQuotaFor($operation)) {
            Log::warning('YouTubeService: Daily quota exhausted', [
                'operation' => $operation,
                'remaining' => $this->rateLimiter->getRemainingDailyQuota(),
            ]);

            return false;
        }

        return true;
    }

    private function trackUsage(string $operation, int $count = 1): void
    {
        $user = $this->userPlatform->user;
        $this->rateLimiter->incrementRequestCount($user);
        $this->rateLimiter->trackQuotaUsage($operation, $count);
    }

    private function client(): PendingRequest
    {
        $this->ensureValidToken();

        return Http::withToken($this->accessToken)
            ->timeout(30)
            ->retry(2, 100);
    }

    private function ensureValidToken(): void
    {
        if ($this->accessToken) {
            return;
        }

        if ($this->userPlatform->token_expires_at &&
            $this->userPlatform->token_expires_at->copy()->subMinutes(5)->isPast()) {
            $this->refreshToken();
        }

        $this->accessToken = $this->userPlatform->access_token;
    }

    public function refreshToken(): bool
    {
        if ($this->isExtensionConnection()) {
            return true;
        }

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

    public function getAccount(): ?array
    {
        if ($this->isExtensionConnection()) {
            return [
                'id' => $this->userPlatform->platform_channel_id,
                'username' => $this->userPlatform->platform_username,
                'name' => $this->userPlatform->platform_username,
                'connection_method' => 'extension',
            ];
        }

        $channel = $this->getChannel();

        if (! $channel) {
            return null;
        }

        return [
            'id' => $channel['id'],
            'username' => $channel['snippet']['title'],
            'name' => $channel['snippet']['title'],
            'profile_picture_url' => $channel['snippet']['thumbnails']['default']['url'] ?? null,
            'followers_count' => $channel['statistics']['subscriberCount'] ?? 0,
            'content_count' => $channel['statistics']['videoCount'] ?? 0,
        ];
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
            Log::error('YouTubeService: Failed to get channel', ['error' => $response->json()]);

            return null;
        }

        $this->cachedChannel = $response->json()['items'][0] ?? null;

        return $this->cachedChannel;
    }

    public function getContents(int $limit = 25, ?string $cursor = null): array
    {
        return $this->getChannelVideos($limit, $cursor);
    }

    public function getChannelVideos(int $maxResults = 50, ?string $pageToken = null): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'items' => [],
                'nextPageToken' => null,
                'message' => 'Content is fetched by browser extension',
            ];
        }

        if (! $this->canMakeRequest('list_videos')) {
            return ['items' => [], 'nextPageToken' => null, 'error' => 'rate_limited'];
        }

        $channel = $this->getChannel();
        if (! $channel) {
            return ['items' => [], 'nextPageToken' => null];
        }

        $uploadsPlaylistId = $channel['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        if (! $uploadsPlaylistId) {
            return ['items' => [], 'nextPageToken' => null];
        }

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
            Log::error('YouTubeService: Failed to get channel videos', ['error' => $response->json()]);

            return ['items' => [], 'nextPageToken' => null];
        }

        $data = $response->json();

        $items = [];
        foreach ($data['items'] ?? [] as $video) {
            $contentId = $video['contentDetails']['videoId']
                ?? $video['snippet']['resourceId']['videoId']
                ?? null;

            if (! $contentId) {
                continue;
            }

            $items[] = [
                'contentId' => $contentId,
                'contentType' => UserContent::TYPE_VIDEO,
                'title' => $video['snippet']['title'] ?? 'Unknown',
                'thumbnail' => $video['snippet']['thumbnails']['medium']['url']
                    ?? $video['snippet']['thumbnails']['default']['url']
                    ?? null,
                'platformUrl' => "https://www.youtube.com/watch?v={$contentId}",
                'publishedAt' => $video['snippet']['publishedAt'] ?? null,
            ];
        }

        return [
            'items' => $items,
            'nextPageToken' => $data['nextPageToken'] ?? null,
            'totalResults' => $data['pageInfo']['totalResults'] ?? 0,
        ];
    }

    public function getComments(string $contentId, int $limit = 50, ?string $cursor = null): array
    {
        return $this->getVideoComments($contentId, $limit, $cursor);
    }

    public function getVideoComments(string $videoId, int $maxResults = 100, ?string $pageToken = null): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'items' => [],
                'nextPageToken' => null,
                'message' => 'Comments are fetched by browser extension',
            ];
        }

        if (! $this->canMakeRequest('list_comments')) {
            return ['items' => [], 'nextPageToken' => null, 'error' => 'rate_limited'];
        }

        $params = [
            'part' => 'snippet',
            'videoId' => $videoId,
            'maxResults' => min($maxResults, 100),
            'order' => 'time',
            'textFormat' => 'plainText',
        ];

        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $response = $this->client()->get(self::API_BASE.'/commentThreads', $params);

        $this->trackUsage('list_comments');

        if ($response->failed()) {
            $error = $response->json();

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

        $comments = [];
        foreach ($data['items'] ?? [] as $item) {
            $topComment = $item['snippet']['topLevelComment'];
            $comments[] = [
                'id' => $topComment['id'],
                'contentId' => $videoId,
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

    public function deleteComment(string $commentId): array
    {
        Log::info('YouTubeService: Attempting to delete comment', [
            'comment_id' => $commentId,
            'user_platform_id' => $this->userPlatform->id,
            'channel_id' => $this->userPlatform->platform_channel_id,
            'connection_method' => $this->userPlatform->connection_method,
        ]);

        if ($this->isExtensionConnection()) {
            return [
                'success' => true,
                'method' => 'extension',
                'message' => 'Deletion queued for browser extension',
            ];
        }

        $result = $this->setModerationStatus($commentId, 'rejected');

        Log::info('YouTubeService: Delete comment result', [
            'comment_id' => $commentId,
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'reason' => $result['reason'] ?? null,
        ]);

        return $result;
    }

    public function hideComment(string $commentId): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'success' => true,
                'method' => 'extension',
                'message' => 'Hide action queued for browser extension',
            ];
        }

        return $this->setModerationStatus($commentId, 'heldForReview');
    }

    public function setModerationStatus(string $commentId, string $status = 'rejected'): array
    {
        Log::info('YouTubeService: setModerationStatus called', [
            'comment_id' => $commentId,
            'status' => $status,
            'user_platform_id' => $this->userPlatform->id,
        ]);

        if (! $this->canMakeRequest('set_moderation_status')) {
            Log::warning('YouTubeService: Cannot make request - rate limited or quota exhausted');

            return ['success' => false, 'error' => 'Rate limit exceeded or quota exhausted'];
        }

        $this->ensureValidToken();

        Log::debug('YouTubeService: Sending API request to setModerationStatus', [
            'endpoint' => self::API_BASE.'/comments/setModerationStatus',
            'comment_id' => $commentId,
            'status' => $status,
            'token_length' => strlen($this->accessToken ?? ''),
        ]);

        $response = Http::withToken($this->accessToken)
            ->asForm()
            ->post(self::API_BASE.'/comments/setModerationStatus', [
                'id' => $commentId,
                'moderationStatus' => $status,
            ]);

        $this->trackUsage('set_moderation_status');

        Log::info('YouTubeService: API response received', [
            'comment_id' => $commentId,
            'http_status' => $response->status(),
            'successful' => $response->successful(),
        ]);

        if ($response->successful() || $response->status() === 204) {
            Log::info('YouTubeService: Comment moderation status set SUCCESSFULLY', [
                'comment_id' => $commentId,
                'status' => $status,
            ]);

            return ['success' => true];
        }

        $error = $response->json();
        Log::error('YouTubeService: Failed to set moderation status', [
            'comment_id' => $commentId,
            'status' => $status,
            'http_status' => $response->status(),
            'error' => $error,
            'raw_body' => $response->body(),
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

    public function testConnection(): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'success' => true,
                'connection_method' => 'extension',
                'channel' => [
                    'id' => $this->userPlatform->platform_channel_id,
                    'title' => $this->userPlatform->platform_username,
                ],
            ];
        }

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
