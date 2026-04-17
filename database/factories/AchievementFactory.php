<?php

namespace Database\Factories;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['first_solve', 'speed_demon', 'streak_7', 'streak_30', 'puzzles_10', 'puzzles_50']),
            'label' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'icon' => 'trophy',
            'earned_at' => now(),
        ];
    }
}
