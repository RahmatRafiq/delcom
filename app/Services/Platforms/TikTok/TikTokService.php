<?php

namespace App\Services\Platforms\TikTok;

use App\Contracts\PlatformServiceInterface;
use App\Models\UserContent;
use App\Models\UserPlatform;
use Illuminate\Support\Facades\Log;

/**
 * TikTok Platform Service
 *
 * This service handles TikTok integration.
 * Note: TikTok only supports extension-based connections.
 * API access is not available due to TikTok's restrictive API policies.
 */
class TikTokService implements PlatformServiceInterface
{
    private UserPlatform $userPlatform;

    public function __construct(UserPlatform $userPlatform)
    {
        $this->userPlatform = $userPlatform;
    }

    public static function for(UserPlatform $userPlatform): self
    {
        return new self($userPlatform);
    }

    public function getPlatformName(): string
    {
        return 'tiktok';
    }

    public function getContentType(): string
    {
        return UserContent::TYPE_VIDEO;
    }

    public function isApiConnection(): bool
    {
        // TikTok does not support API connections
        return false;
    }

    public function isExtensionConnection(): bool
    {
        return $this->userPlatform->connection_method === 'extension';
    }

    public function refreshToken(): bool
    {
        // Extension connections don't require token refresh
        if ($this->isExtensionConnection()) {
            return true;
        }

        // API connections would require token refresh, but TikTok API is not supported
        Log::warning('TikTokService: API connections not supported', [
            'user_platform_id' => $this->userPlatform->id,
        ]);

        return false;
    }

    public function getAccount(): ?array
    {
        if ($this->isExtensionConnection()) {
            return [
                'id' => $this->userPlatform->platform_user_id,
                'username' => $this->userPlatform->platform_username,
                'connection_method' => 'extension',
            ];
        }

        // API connections not supported
        return null;
    }

    public function getContents(int $limit = 25, ?string $cursor = null): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'items' => [],
                'paging' => null,
                'message' => 'Content is fetched by browser extension',
            ];
        }

        // API access not available
        return ['items' => [], 'paging' => null];
    }

    public function getComments(string $contentId, int $limit = 50, ?string $cursor = null): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'items' => [],
                'paging' => null,
                'message' => 'Comments are fetched by browser extension',
            ];
        }

        // API access not available
        return ['items' => [], 'paging' => null];
    }

    public function deleteComment(string $commentId): array
    {
        Log::info('TikTokService: Delete comment request', [
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

        return [
            'success' => false,
            'error' => 'TikTok API access not available. Please use browser extension.',
        ];
    }

    public function hideComment(string $commentId): array
    {
        Log::info('TikTokService: Hide comment request', [
            'comment_id' => $commentId,
            'connection_method' => $this->userPlatform->connection_method,
        ]);

        if ($this->isExtensionConnection()) {
            return [
                'success' => true,
                'method' => 'extension',
                'message' => 'Hide action queued for browser extension',
            ];
        }

        return [
            'success' => false,
            'error' => 'TikTok API access not available. Please use browser extension.',
        ];
    }

    public function testConnection(): array
    {
        if ($this->isExtensionConnection()) {
            return [
                'success' => true,
                'connection_method' => 'extension',
                'username' => $this->userPlatform->platform_username,
            ];
        }

        return [
            'success' => false,
            'error' => 'TikTok only supports extension-based connections',
        ];
    }
}
