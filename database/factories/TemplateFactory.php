<?php

namespace Database\Factories;

use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $size = 15;

        return [
            'name' => fake()->words(2, true),
            'width' => $size,
            'height' => $size,
            'grid' => self::openGrid($size, $size),
            'min_word_length' => 3,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function square(int $size): static
    {
        return $this->state(fn () => [
            'width' => $size,
            'height' => $size,
            'grid' => self::openGrid($size, $size),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    public static function openGrid(int $width, int $height): array
    {
        $row = array_fill(0, $width, 0);

        return array_fill(0, $height, $row);
    }
}
