<?php

namespace Database\Factories;

use App\Models\FilterGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FilterGroupFactory extends Factory
{
    protected $model = FilterGroup::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'applies_to_platforms' => null,
            'is_active' => true,
        ];
    }

    public function youtube(): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to_platforms' => ['youtube'],
        ]);
    }

    public function instagram(): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to_platforms' => ['instagram'],
        ]);
    }

    public function allPlatforms(): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to_platforms' => null,
        ]);
    }
}
