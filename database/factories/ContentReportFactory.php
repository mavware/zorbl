<?php

namespace Database\Factories;

use App\Models\ContentReport;
use App\Models\Crossword;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentReport>
 */
class ContentReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => Crossword::class,
            'reportable_id' => Crossword::factory(),
            'reason' => array_rand(ContentReport::REASONS),
            'details' => fake()->optional(0.5)->sentence(),
            'status' => ContentReport::STATUS_PENDING,
        ];
    }
}
