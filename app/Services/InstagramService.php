<?php

namespace App\Services;

use App\Models\UserPlatform;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Service
 *
 * Supports two connection methods:
 * 1. Extension - Browser extension sends data to our API
 * 2. API (Future) - Instagram Graph API via Meta OAuth
 *
 * For now, this service primarily handles extension-based connections.
 * API support will be added when Meta Developer App is approved.
 */
class InstagramService
{
    private UserPlatform $userPlatform;

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
     * Get account info.
     * For extension: returns stored username
     * For API: would call Instagram Graph API
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

        // API connection - will be implemented when Meta App is ready
        return $this->getAccountViaApi();
    }

    /**
     * Get account via Instagram Graph API.
     * Placeholder for future implementation.
     */
    private function getAccountViaApi(): ?array
    {
        // TODO: Implement when Meta Developer App is approved
        // Will use Instagram Graph API:
        // GET /me?fields=id,username,account_type,media_count
        Log::info('InstagramService: API connection not yet implemented');

        return null;
    }

    /**
     * Get posts/media from the account.
     * For extension: data is sent from extension, not fetched
     * For API: would call Instagram Graph API
     */
    public function getContents(int $maxResults = 50, ?string $pageToken = null): array
    {
        if ($this->isExtensionConnection()) {
            // Extension sends content data to us, we don't fetch it
            Log::info('InstagramService: Extension mode - content fetched by extension');

            return [
                'items' => [],
                'nextPageToken' => null,
                'message' => 'Content is fetched by browser extension',
            ];
        }

        // API connection - will be implemented when Meta App is ready
        return $this->getContentsViaApi($maxResults, $pageToken);
    }

    /**
     * Get contents via Instagram Graph API.
     * Placeholder for future implementation.
     */
    private function getContentsViaApi(int $maxResults, ?string $pageToken): array
    {
        // TODO: Implement when Meta Developer App is approved
        // Will use Instagram Graph API:
        // GET /me/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,comments_count
        Log::info('InstagramService: API getContents not yet implemented');

        return ['items' => [], 'nextPageToken' => null];
    }

    /**
     * Get comments for a specific post.
     * For extension: data is sent from extension
     * For API: would call Instagram Graph API
     */
    public function getComments(string $mediaId, int $maxResults = 100, ?string $pageToken = null): array
    {
        if ($this->isExtensionConnection()) {
            // Extension sends comment data to us, we don't fetch it
            Log::info('InstagramService: Extension mode - comments fetched by extension');

            return [
                'items' => [],
                'nextPageToken' => null,
                'message' => 'Comments are fetched by browser extension',
            ];
        }

        // API connection - will be implemented when Meta App is ready
        return $this->getCommentsViaApi($mediaId, $maxResults, $pageToken);
    }

    /**
     * Get comments via Instagram Graph API.
     * Placeholder for future implementation.
     */
    private function getCommentsViaApi(string $mediaId, int $maxResults, ?string $pageToken): array
    {
        // TODO: Implement when Meta Developer App is approved
        // Will use Instagram Graph API:
        // GET /{media-id}/comments?fields=id,text,timestamp,username,like_count,replies{id,text,username,timestamp}
        Log::info('InstagramService: API getComments not yet implemented');

        return ['items' => [], 'nextPageToken' => null];
    }

    /**
     * Delete a comment.
     * For extension: returns instruction for extension to delete
     * For API: would call Instagram Graph API
     */
    public function deleteComment(string $commentId): array
    {
        Log::info('InstagramService: Attempting to delete comment', [
            'comment_id' => $commentId,
            'user_platform_id' => $this->userPlatform->id,
            'connection_method' => $this->userPlatform->connection_method,
        ]);

        if ($this->isExtensionConnection()) {
            // Extension handles deletion - we just mark it as pending
            // The extension will poll for pending deletions and execute them
            return [
                'success' => true,
                'method' => 'extension',
                'message' => 'Deletion queued for browser extension',
            ];
        }

        // API connection - will be implemented when Meta App is ready
        return $this->deleteCommentViaApi($commentId);
    }

    /**
     * Delete comment via Instagram Graph API.
     * Placeholder for future implementation.
     */
    private function deleteCommentViaApi(string $commentId): array
    {
        // TODO: Implement when Meta Developer App is approved
        // Will use Instagram Graph API:
        // DELETE /{comment-id}
        Log::info('InstagramService: API deleteComment not yet implemented');

        return [
            'success' => false,
            'error' => 'Instagram API not yet configured. Please use browser extension.',
        ];
    }

    /**
     * Hide a comment (alternative to delete).
     * For extension: returns instruction for extension
     * For API: would call Instagram Graph API
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

        // API connection
        return $this->hideCommentViaApi($commentId, $hide);
    }

    /**
     * Hide comment via Instagram Graph API.
     * Placeholder for future implementation.
     */
    private function hideCommentViaApi(string $commentId, bool $hide): array
    {
        // TODO: Implement when Meta Developer App is approved
        // Will use Instagram Graph API:
        // POST /{comment-id}?hide=true (or false to unhide)
        Log::info('InstagramService: API hideComment not yet implemented');

        return [
            'success' => false,
            'error' => 'Instagram API not yet configured.',
        ];
    }

    /**
     * Test connection.
     */
    public function testConnection(): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'success' => true,
                'connection_method' => 'extension',
                'username' => $this->userPlatform->platform_username,
                'message' => 'Extension connection active',
            ];
        }

        // For API, would test by calling /me endpoint
        return [
            'success' => false,
            'error' => 'Instagram API not yet configured',
        ];
    }

    /**
     * Refresh OAuth token.
     * Only applicable for API connections.
     */
    public function refreshToken(): bool
    {
        if ($this->isExtensionConnection()) {
            // Extension doesn't use OAuth tokens
            return true;
        }

        // TODO: Implement token refresh for API connection
        // Instagram long-lived tokens last 60 days and need refresh
        // POST /refresh_access_token?grant_type=ig_refresh_token&access_token={token}
        Log::info('InstagramService: Token refresh not yet implemented');

        return false;
    }
}
