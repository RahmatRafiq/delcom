<?php

namespace Database\Factories;

use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformFactory extends Factory
{
    protected $model = Platform::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['youtube', 'instagram', 'tiktok', 'twitter']),
            'display_name' => $this->faker->company(),
            'is_active' => true,
            'tier' => $this->faker->randomElement(['api', 'extension']),
        ];
    }

    public function youtube(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'youtube',
            'display_name' => 'YouTube',
        ]);
    }

    public function instagram(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'instagram',
            'display_name' => 'Instagram',
        ]);
    }
}
