<?php

namespace Database\Factories;

use App\Models\ClueEntry;
use App\Models\ClueReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClueReport>
 */
class ClueReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clue_entry_id' => ClueEntry::factory(),
            'user_id' => User::factory(),
            'reason' => fake()->randomElement(['inaccurate', 'offensive', 'duplicate', 'low_quality']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
