<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'owner_id' => User::factory(),
        ];
    }

    public function withOwnerAsMember(): static
    {
        return $this->afterCreating(function (Team $team): void {
            $team->members()->attach($team->owner_id, ['role' => TeamRole::Owner->value]);
        });
    }
}
