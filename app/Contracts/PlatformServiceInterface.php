<?php

namespace App\Contracts;

use App\Models\UserPlatform;

interface PlatformServiceInterface
{
    public static function for(UserPlatform $userPlatform): self;

    public function getAccount(): ?array;

    public function getContents(int $limit = 25, ?string $cursor = null): array;

    public function getComments(string $contentId, int $limit = 50, ?string $cursor = null): array;

    public function deleteComment(string $commentId): array;

    public function hideComment(string $commentId): array;

    public function testConnection(): array;

    public function refreshToken(): bool;

    public function isApiConnection(): bool;

    public function isExtensionConnection(): bool;

    public function getContentType(): string;

    public function getPlatformName(): string;
}
