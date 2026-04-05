<?php

namespace Database\Factories;

use App\Models\WorkLogCategoryGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkLogCategoryGroup>
 */
class WorkLogCategoryGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'sort_order' => $this->faker->numberBetween(0, 99),
        ];
    }
}
