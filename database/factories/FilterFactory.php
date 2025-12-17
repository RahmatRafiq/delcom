<?php

namespace Database\Factories;

use App\Models\Filter;
use App\Models\FilterGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class FilterFactory extends Factory
{
    protected $model = Filter::class;

    public function definition(): array
    {
        return [
            'filter_group_id' => FilterGroup::factory(),
            'type' => $this->faker->randomElement(['keyword', 'phrase', 'regex', 'username']),
            'pattern' => $this->faker->word(),
            'match_type' => 'contains',
            'case_sensitive' => false,
            'action' => $this->faker->randomElement(['delete', 'hide', 'flag', 'report']),
            'priority' => $this->faker->numberBetween(1, 100),
            'hit_count' => 0,
            'is_active' => true,
        ];
    }

    public function keyword(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'keyword',
        ]);
    }

    public function regex(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'regex',
        ]);
    }

    public function deleteAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'delete',
        ]);
    }
}
