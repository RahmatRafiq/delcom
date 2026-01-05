<?php

namespace App\Services;

use App\Contracts\PlatformServiceInterface;
use App\Models\UserPlatform;
use App\Services\Platforms\Instagram\InstagramService;
use App\Services\Platforms\TikTok\TikTokService;
use App\Services\Platforms\Youtube\YouTubeService;
use InvalidArgumentException;

class PlatformServiceFactory
{
    protected static array $services = [
        'youtube' => YouTubeService::class,
        'instagram' => InstagramService::class,
        'tiktok' => TikTokService::class,
    ];

    public static function make(UserPlatform $userPlatform): PlatformServiceInterface
    {
        $platformName = strtolower($userPlatform->platform->name);

        if (! isset(self::$services[$platformName])) {
            throw new InvalidArgumentException("Unsupported platform: {$platformName}");
        }

        $serviceClass = self::$services[$platformName];

        return $serviceClass::for($userPlatform);
    }

    public static function supports(string $platformName): bool
    {
        return isset(self::$services[strtolower($platformName)]);
    }

    public static function getSupportedPlatforms(): array
    {
        return array_keys(self::$services);
    }

    public static function register(string $platformName, string $serviceClass): void
    {
        self::$services[strtolower($platformName)] = $serviceClass;
    }
}
