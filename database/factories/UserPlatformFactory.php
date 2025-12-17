<?php

namespace Database\Factories;

use App\Models\Platform;
use App\Models\User;
use App\Models\UserPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPlatformFactory extends Factory
{
    protected $model = UserPlatform::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform_id' => Platform::factory(),
            'platform_user_id' => $this->faker->uuid(),
            'platform_username' => $this->faker->userName(),
            'platform_channel_id' => 'UC'.$this->faker->regexify('[A-Za-z0-9]{22}'),
            'connection_method' => 'api',
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'token_expires_at' => now()->addDays(30),
            'is_active' => true,
            'auto_moderation_enabled' => false,
            'auto_delete_enabled' => false,
            'scan_mode' => 'incremental',
            'scan_frequency_minutes' => 60,
        ];
    }

    public function api(): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_method' => 'api',
        ]);
    }

    public function extension(): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_method' => 'extension',
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function withAutoModeration(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_moderation_enabled' => true,
        ]);
    }

    public function withAutoDelete(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_delete_enabled' => true,
        ]);
    }
}
