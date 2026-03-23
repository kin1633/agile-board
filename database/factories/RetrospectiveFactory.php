<?php

namespace Database\Factories;

use App\Models\Retrospective;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Retrospective>
 */
class RetrospectiveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sprint_id' => Sprint::factory(),
            'type' => fake()->randomElement(['keep', 'problem', 'try']),
            'content' => fake()->paragraph(),
        ];
    }
}
