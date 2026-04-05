<?php

namespace Database\Factories;

use App\Models\WorkLogCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkLogCategory>
 */
class WorkLogCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'value' => 'custom_'.$this->faker->unique()->regexify('[a-z0-9]{8}'),
            'label' => $this->faker->word(),
            'work_log_category_group_id' => null,
            'color' => '#3b82f6',
            'is_default' => false,
            'sort_order' => $this->faker->numberBetween(10, 99),
            'is_active' => true,
        ];
    }
}
