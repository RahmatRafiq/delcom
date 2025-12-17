<?php

use App\Models\Platform;
use App\Models\User;
use App\Models\UserPlatform;
use App\Services\Platforms\Youtube\YouTubeService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->platform = Platform::factory()->create(['name' => 'youtube']);
});

describe('YouTubeService', function () {
    describe('connection method detection', function () {
        it('detects API connection', function () {
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $this->platform->id,
                'connection_method' => 'api',
            ]);

            $service = YouTubeService::for($userPlatform);

            expect($service->isApiConnection())->toBeTrue();
            expect($service->isExtensionConnection())->toBeFalse();
        });

        it('detects Extension connection', function () {
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $this->platform->id,
                'connection_method' => 'extension',
            ]);

            $service = YouTubeService::for($userPlatform);

            expect($service->isApiConnection())->toBeFalse();
            expect($service->isExtensionConnection())->toBeTrue();
        });
    });

    describe('extension connection handling', function () {
        beforeEach(function () {
            $this->userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $this->platform->id,
                'connection_method' => 'extension',
                'platform_channel_id' => 'UC123456',
                'platform_username' => 'TestChannel',
            ]);
            $this->service = YouTubeService::for($this->userPlatform);
        });

        it('returns extension info for getAccount', function () {
            $account = $this->service->getAccount();

            expect($account)->toBeArray();
            expect($account['connection_method'])->toBe('extension');
            expect($account['id'])->toBe('UC123456');
            expect($account['username'])->toBe('TestChannel');
        });

        it('returns message for getContents', function () {
            $contents = $this->service->getContents();

            expect($contents)->toBeArray();
            expect($contents['items'])->toBeEmpty();
            expect($contents['message'])->toBe('Content is fetched by browser extension');
        });

        it('returns message for getComments', function () {
            $comments = $this->service->getComments('video123');

            expect($comments)->toBeArray();
            expect($comments['items'])->toBeEmpty();
            expect($comments['message'])->toBe('Comments are fetched by browser extension');
        });

        it('returns queued for deleteComment', function () {
            $result = $this->service->deleteComment('comment123');

            expect($result)->toBeArray();
            expect($result['success'])->toBeTrue();
            expect($result['method'])->toBe('extension');
            expect($result['message'])->toBe('Deletion queued for browser extension');
        });

        it('returns queued for hideComment', function () {
            $result = $this->service->hideComment('comment123');

            expect($result)->toBeArray();
            expect($result['success'])->toBeTrue();
            expect($result['method'])->toBe('extension');
            expect($result['message'])->toBe('Hide action queued for browser extension');
        });

        it('returns success for testConnection', function () {
            $result = $this->service->testConnection();

            expect($result)->toBeArray();
            expect($result['success'])->toBeTrue();
            expect($result['connection_method'])->toBe('extension');
            expect($result['channel']['id'])->toBe('UC123456');
        });

        it('returns true for refreshToken', function () {
            $result = $this->service->refreshToken();

            expect($result)->toBeTrue();
        });
    });

    describe('platform info', function () {
        it('returns correct platform name', function () {
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $this->platform->id,
            ]);

            $service = YouTubeService::for($userPlatform);

            expect($service->getPlatformName())->toBe('youtube');
        });

        it('returns correct content type', function () {
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $this->platform->id,
            ]);

            $service = YouTubeService::for($userPlatform);

            expect($service->getContentType())->toBe('video');
        });
    });
});
