<?php

namespace Database\Factories;

use App\Models\Achievement;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(array_keys(AchievementService::DEFINITIONS));
        $def = AchievementService::DEFINITIONS[$type];

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'label' => $def['label'],
            'description' => $def['description'],
            'icon' => $def['icon'],
            'earned_at' => now(),
        ];
    }

    public function type(string $type): static
    {
        $def = AchievementService::DEFINITIONS[$type];

        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'label' => $def['label'],
            'description' => $def['description'],
            'icon' => $def['icon'],
        ]);
    }
}
