<?php

use App\Models\Platform;
use App\Models\User;
use App\Models\UserPlatform;
use App\Services\Platforms\Instagram\InstagramService;
use App\Services\Platforms\Youtube\YouTubeService;
use App\Services\PlatformServiceFactory;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('PlatformServiceFactory', function () {
    it('creates YouTubeService for youtube platform', function () {
        $platform = Platform::factory()->create(['name' => 'youtube']);
        $userPlatform = UserPlatform::factory()->create([
            'user_id' => $this->user->id,
            'platform_id' => $platform->id,
            'connection_method' => 'api',
        ]);

        $service = PlatformServiceFactory::make($userPlatform);

        expect($service)->toBeInstanceOf(YouTubeService::class);
        expect($service->getPlatformName())->toBe('youtube');
    });

    it('creates InstagramService for instagram platform', function () {
        $platform = Platform::factory()->create(['name' => 'instagram']);
        $userPlatform = UserPlatform::factory()->create([
            'user_id' => $this->user->id,
            'platform_id' => $platform->id,
            'connection_method' => 'api',
        ]);

        $service = PlatformServiceFactory::make($userPlatform);

        expect($service)->toBeInstanceOf(InstagramService::class);
        expect($service->getPlatformName())->toBe('instagram');
    });

    it('throws exception for unsupported platform', function () {
        $platform = Platform::factory()->create(['name' => 'tiktok']);
        $userPlatform = UserPlatform::factory()->create([
            'user_id' => $this->user->id,
            'platform_id' => $platform->id,
        ]);

        PlatformServiceFactory::make($userPlatform);
    })->throws(InvalidArgumentException::class, 'Unsupported platform: tiktok');

    it('returns true for supported platforms', function () {
        expect(PlatformServiceFactory::supports('youtube'))->toBeTrue();
        expect(PlatformServiceFactory::supports('instagram'))->toBeTrue();
        expect(PlatformServiceFactory::supports('YouTube'))->toBeTrue();
        expect(PlatformServiceFactory::supports('INSTAGRAM'))->toBeTrue();
    });

    it('returns false for unsupported platforms', function () {
        expect(PlatformServiceFactory::supports('tiktok'))->toBeFalse();
        expect(PlatformServiceFactory::supports('twitter'))->toBeFalse();
        expect(PlatformServiceFactory::supports('facebook'))->toBeFalse();
    });

    it('returns list of supported platforms', function () {
        $platforms = PlatformServiceFactory::getSupportedPlatforms();

        expect($platforms)->toContain('youtube');
        expect($platforms)->toContain('instagram');
    });

    it('allows registering new platform services', function () {
        PlatformServiceFactory::register('tiktok', YouTubeService::class);

        expect(PlatformServiceFactory::supports('tiktok'))->toBeTrue();
    });
});
