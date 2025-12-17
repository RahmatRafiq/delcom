<?php

namespace App\Services;

use App\Models\UserPlatform;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Service
 *
 * Supports two connection methods:
 * 1. API - Instagram Graph API via Facebook OAuth
 * 2. Extension - Browser extension sends data to our API
 */
class InstagramService
{
    private const GRAPH_API_BASE = 'https://graph.facebook.com/v21.0';

    private UserPlatform $userPlatform;

    private ?string $accessToken = null;

    private ?array $cachedAccount = null;

    public function __construct(UserPlatform $userPlatform)
    {
        $this->userPlatform = $userPlatform;
    }

    /**
     * Create instance from UserPlatform.
     */
    public static function for(UserPlatform $userPlatform): self
    {
        return new self($userPlatform);
    }

    /**
     * Check if this is an API connection.
     */
    public function isApiConnection(): bool
    {
        return $this->userPlatform->connection_method === 'api';
    }

    /**
     * Check if this is an extension connection.
     */
    public function isExtensionConnection(): bool
    {
        return $this->userPlatform->connection_method === 'extension';
    }

    /**
     * Get the HTTP client.
     */
    private function client(): PendingRequest
    {
        return Http::timeout(30)->retry(2, 100);
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
     * Refresh the OAuth access token (exchange for long-lived token).
     */
    public function refreshToken(): bool
    {
        if ($this->isExtensionConnection()) {
            return true; // Extension doesn't use OAuth tokens
        }

        $currentToken = $this->userPlatform->access_token;

        if (! $currentToken) {
            Log::error('InstagramService: No access token available', [
                'user_platform_id' => $this->userPlatform->id,
            ]);

            return false;
        }

        try {
            // Exchange for long-lived token
            $response = Http::get(self::GRAPH_API_BASE.'/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.facebook.client_id'),
                'client_secret' => config('services.facebook.client_secret'),
                'fb_exchange_token' => $currentToken,
            ]);

            if ($response->failed()) {
                Log::error('InstagramService: Token refresh failed', [
                    'user_platform_id' => $this->userPlatform->id,
                    'error' => $response->json(),
                ]);

                return false;
            }

            $data = $response->json();

            $this->userPlatform->update([
                'access_token' => $data['access_token'],
                // Long-lived tokens last ~60 days
                'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 5184000),
            ]);

            $this->accessToken = $data['access_token'];

            Log::info('InstagramService: Token refreshed successfully', [
                'user_platform_id' => $this->userPlatform->id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('InstagramService: Token refresh exception', [
                'user_platform_id' => $this->userPlatform->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get account info.
     */
    public function getAccount(): ?array
    {
        if ($this->isExtensionConnection()) {
            return [
                'id' => $this->userPlatform->platform_user_id,
                'username' => $this->userPlatform->platform_username,
                'connection_method' => 'extension',
            ];
        }

        return $this->getAccountViaApi();
    }

    /**
     * Get account via Instagram Graph API.
     */
    private function getAccountViaApi(): ?array
    {
        if ($this->cachedAccount !== null) {
            return $this->cachedAccount;
        }

        $igUserId = $this->userPlatform->platform_user_id;

        if (! $igUserId) {
            Log::error('InstagramService: No Instagram user ID', [
                'user_platform_id' => $this->userPlatform->id,
            ]);

            return null;
        }

        $this->ensureValidToken();

        $response = $this->client()->get(self::GRAPH_API_BASE."/{$igUserId}", [
            'fields' => 'id,username,name,profile_picture_url,followers_count,media_count,biography',
            'access_token' => $this->accessToken,
        ]);

        if ($response->failed()) {
            Log::error('InstagramService: Failed to get account', [
                'error' => $response->json(),
            ]);

            return null;
        }

        $this->cachedAccount = $response->json();

        return $this->cachedAccount;
    }

    /**
     * Get user's media (posts, reels).
     */
    public function getMedia(int $limit = 25, ?string $after = null): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'items' => [],
                'paging' => null,
                'message' => 'Content is fetched by browser extension',
            ];
        }

        $igUserId = $this->userPlatform->platform_user_id;

        if (! $igUserId) {
            return ['items' => [], 'paging' => null];
        }

        $this->ensureValidToken();

        $params = [
            'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count',
            'limit' => min($limit, 100),
            'access_token' => $this->accessToken,
        ];

        if ($after) {
            $params['after'] = $after;
        }

        $response = $this->client()->get(self::GRAPH_API_BASE."/{$igUserId}/media", $params);

        if ($response->failed()) {
            Log::error('InstagramService: Failed to get media', [
                'error' => $response->json(),
            ]);

            return ['items' => [], 'paging' => null];
        }

        $data = $response->json();

        return [
            'items' => $data['data'] ?? [],
            'paging' => $data['paging'] ?? null,
        ];
    }

    /**
     * Get comments for a specific media.
     */
    public function getComments(string $mediaId, int $limit = 50, ?string $after = null): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'items' => [],
                'paging' => null,
                'message' => 'Comments are fetched by browser extension',
            ];
        }

        $this->ensureValidToken();

        $params = [
            'fields' => 'id,text,username,timestamp,like_count,replies{id,text,username,timestamp}',
            'limit' => min($limit, 50),
            'access_token' => $this->accessToken,
        ];

        if ($after) {
            $params['after'] = $after;
        }

        $response = $this->client()->get(self::GRAPH_API_BASE."/{$mediaId}/comments", $params);

        if ($response->failed()) {
            $error = $response->json();

            // Check if comments are disabled or restricted
            if (isset($error['error']['code']) && $error['error']['code'] === 100) {
                Log::info('InstagramService: Comments may be disabled', ['media_id' => $mediaId]);

                return ['items' => [], 'paging' => null, 'commentsDisabled' => true];
            }

            Log::error('InstagramService: Failed to get comments', [
                'media_id' => $mediaId,
                'error' => $error,
            ]);

            return ['items' => [], 'paging' => null];
        }

        $data = $response->json();

        // Transform to consistent format
        $comments = [];
        foreach ($data['data'] ?? [] as $comment) {
            $comments[] = [
                'id' => $comment['id'],
                'text' => $comment['text'],
                'username' => $comment['username'],
                'timestamp' => $comment['timestamp'],
                'likeCount' => $comment['like_count'] ?? 0,
                'replies' => $comment['replies']['data'] ?? [],
            ];
        }

        return [
            'items' => $comments,
            'paging' => $data['paging'] ?? null,
        ];
    }

    /**
     * Delete a comment.
     */
    public function deleteComment(string $commentId): array
    {
        Log::info('InstagramService: Attempting to delete comment', [
            'comment_id' => $commentId,
            'user_platform_id' => $this->userPlatform->id,
            'connection_method' => $this->userPlatform->connection_method,
        ]);

        if ($this->isExtensionConnection()) {
            return [
                'success' => true,
                'method' => 'extension',
                'message' => 'Deletion queued for browser extension',
            ];
        }

        $this->ensureValidToken();

        $response = $this->client()->delete(self::GRAPH_API_BASE."/{$commentId}", [
            'access_token' => $this->accessToken,
        ]);

        if ($response->successful()) {
            Log::info('InstagramService: Comment deleted successfully', [
                'comment_id' => $commentId,
            ]);

            return ['success' => true];
        }

        $error = $response->json();
        Log::error('InstagramService: Failed to delete comment', [
            'comment_id' => $commentId,
            'error' => $error,
        ]);

        return [
            'success' => false,
            'error' => $error['error']['message'] ?? 'Unknown error',
            'code' => $error['error']['code'] ?? null,
        ];
    }

    /**
     * Hide a comment.
     */
    public function hideComment(string $commentId, bool $hide = true): array
    {
        Log::info('InstagramService: Attempting to hide comment', [
            'comment_id' => $commentId,
            'hide' => $hide,
            'connection_method' => $this->userPlatform->connection_method,
        ]);

        if ($this->isExtensionConnection()) {
            return [
                'success' => true,
                'method' => 'extension',
                'message' => 'Hide action queued for browser extension',
            ];
        }

        $this->ensureValidToken();

        $response = $this->client()->post(self::GRAPH_API_BASE."/{$commentId}", [
            'hide' => $hide,
            'access_token' => $this->accessToken,
        ]);

        if ($response->successful()) {
            Log::info('InstagramService: Comment hidden', ['comment_id' => $commentId, 'hidden' => $hide]);

            return ['success' => true];
        }

        $error = $response->json();
        Log::error('InstagramService: Failed to hide comment', [
            'comment_id' => $commentId,
            'error' => $error,
        ]);

        return [
            'success' => false,
            'error' => $error['error']['message'] ?? 'Unknown error',
        ];
    }

    /**
     * Reply to a comment.
     */
    public function replyToComment(string $commentId, string $message): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'success' => false,
                'error' => 'Reply not supported via extension',
            ];
        }

        $this->ensureValidToken();

        $response = $this->client()->post(self::GRAPH_API_BASE."/{$commentId}/replies", [
            'message' => $message,
            'access_token' => $this->accessToken,
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'reply_id' => $response->json()['id'] ?? null,
            ];
        }

        $error = $response->json();
        Log::error('InstagramService: Failed to reply', [
            'comment_id' => $commentId,
            'error' => $error,
        ]);

        return [
            'success' => false,
            'error' => $error['error']['message'] ?? 'Unknown error',
        ];
    }

    /**
     * Get Facebook Pages that user manages (needed to find Instagram accounts).
     */
    public static function getFacebookPages(string $accessToken): array
    {
        $response = Http::get(self::GRAPH_API_BASE.'/me/accounts', [
            'fields' => 'id,name,access_token,instagram_business_account{id,username,name,profile_picture_url,followers_count}',
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('InstagramService: Failed to get Facebook pages', [
                'error' => $response->json(),
            ]);

            return [];
        }

        return $response->json()['data'] ?? [];
    }

    /**
     * Test API connection.
     */
    public function testConnection(): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'success' => true,
                'connection_method' => 'extension',
                'username' => $this->userPlatform->platform_username,
            ];
        }

        try {
            $account = $this->getAccount();

            if ($account) {
                return [
                    'success' => true,
                    'account' => [
                        'id' => $account['id'],
                        'username' => $account['username'] ?? null,
                        'name' => $account['name'] ?? null,
                        'followers_count' => $account['followers_count'] ?? 0,
                        'media_count' => $account['media_count'] ?? 0,
                    ],
                ];
            }

            return ['success' => false, 'error' => 'Could not retrieve account'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
