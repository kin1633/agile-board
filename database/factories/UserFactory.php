<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'github_id' => fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'avatar' => fake()->imageUrl(100, 100, 'people'),
            // テスト用ダミートークン（暗号化はEloquentキャストが自動適用）
            'github_token' => 'test_token_'.fake()->lexify('??????????'),
        ];
    }
}
