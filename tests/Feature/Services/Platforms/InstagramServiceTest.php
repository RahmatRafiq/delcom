<?php

use App\Models\Platform;
use App\Models\User;
use App\Models\UserPlatform;
use App\Services\Platforms\Instagram\InstagramService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->platform = Platform::factory()->create(['name' => 'instagram']);
});

describe('InstagramService', function () {
    describe('connection method detection', function () {
        it('detects API connection', function () {
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $this->platform->id,
                'connection_method' => 'api',
            ]);

            $service = InstagramService::for($userPlatform);

            expect($service->isApiConnection())->toBeTrue();
            expect($service->isExtensionConnection())->toBeFalse();
        });

        it('detects Extension connection', function () {
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $this->platform->id,
                'connection_method' => 'extension',
            ]);

            $service = InstagramService::for($userPlatform);

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
                'platform_user_id' => '123456789',
                'platform_username' => 'test_instagram',
            ]);
            $this->service = InstagramService::for($this->userPlatform);
        });

        it('returns extension info for getAccount', function () {
            $account = $this->service->getAccount();

            expect($account)->toBeArray();
            expect($account['connection_method'])->toBe('extension');
            expect($account['id'])->toBe('123456789');
            expect($account['username'])->toBe('test_instagram');
        });

        it('returns message for getContents', function () {
            $contents = $this->service->getContents();

            expect($contents)->toBeArray();
            expect($contents['items'])->toBeEmpty();
            expect($contents['message'])->toBe('Content is fetched by browser extension');
        });

        it('returns message for getComments', function () {
            $comments = $this->service->getComments('media123');

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
            expect($result['username'])->toBe('test_instagram');
        });

        it('returns true for refreshToken', function () {
            $result = $this->service->refreshToken();

            expect($result)->toBeTrue();
        });

        it('returns error for replyToComment', function () {
            $result = $this->service->replyToComment('comment123', 'test reply');

            expect($result)->toBeArray();
            expect($result['success'])->toBeFalse();
            expect($result['error'])->toBe('Reply not supported via extension');
        });
    });

    describe('platform info', function () {
        it('returns correct platform name', function () {
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $this->platform->id,
            ]);

            $service = InstagramService::for($userPlatform);

            expect($service->getPlatformName())->toBe('instagram');
        });

        it('returns correct content type', function () {
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $this->platform->id,
            ]);

            $service = InstagramService::for($userPlatform);

            expect($service->getContentType())->toBe('post');
        });
    });
});
