<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    /**
     * Seed the templates table with the curated layouts that currently exist
     * in the production database. Idempotent: re-running will not create
     * duplicates of templates that share name + dimensions.
     */
    public function run(): void
    {
        foreach (self::templates() as $template) {
            Template::firstOrCreate(
                [
                    'name' => $template['name'],
                    'width' => $template['width'],
                    'height' => $template['height'],
                ],
                [
                    'grid' => $template['grid'],
                    'styles' => $template['styles'] ?? null,
                    'min_word_length' => $template['min_word_length'],
                    'sort_order' => $template['sort_order'],
                    'is_active' => $template['is_active'],
                ],
            );
        }
    }

    /**
     * @return list<array{
     *     name: string,
     *     width: int,
     *     height: int,
     *     grid: array<int, array<int, int|string>>,
     *     styles?: array<string, array{bars?: list<string>}>|null,
     *     min_word_length: int,
     *     sort_order: int,
     *     is_active: bool,
     * }>
     */
    private static function templates(): array
    {
        return [
            [
                'name' => 'Two Quadrants',
                'width' => 5,
                'height' => 5,
                'grid' => [
                    ['#', '#', 0, 0, 0],
                    ['#', '#', 0, 0, 0],
                    [0, 0, 0, 0, 0],
                    [0, 0, 0, '#', '#'],
                    [0, 0, 0, '#', '#'],
                ],
                'min_word_length' => 3,
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Horizontal Corners',
                'width' => 5,
                'height' => 5,
                'grid' => [
                    ['#', '#', 0, 0, 0],
                    [0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0],
                    [0, 0, 0, '#', '#'],
                ],
                'min_word_length' => 3,
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Vertical Corners',
                'width' => 5,
                'height' => 5,
                'grid' => [
                    ['#', 0, 0, 0, 0],
                    ['#', 0, 0, 0, 0],
                    [0, 0, 0, 0, 0],
                    [0, 0, 0, 0, '#'],
                    [0, 0, 0, 0, '#'],
                ],
                'min_word_length' => 3,
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Blocky Asymetrical',
                'width' => 5,
                'height' => 5,
                'grid' => [
                    [0, 0, 0, '#', '#'],
                    [0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0],
                    ['#', '#', 0, 0, 0],
                    ['#', '#', 0, 0, 0],
                ],
                'min_word_length' => 3,
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Corner Edges',
                'width' => 5,
                'height' => 5,
                'grid' => [
                    [0, 0, 0, '#', '#'],
                    [0, 0, 0, 0, '#'],
                    [0, 0, 0, 0, 0],
                    ['#', 0, 0, 0, 0],
                    ['#', '#', 0, 0, 0],
                ],
                'min_word_length' => 3,
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Airy Asymetrical',
                'width' => 5,
                'height' => 5,
                'grid' => [
                    [0, 0, 0, 0, '#'],
                    [0, '#', 0, 0, 0],
                    [0, '#', 0, 0, 0],
                    [0, '#', 0, 0, 0],
                    [0, 0, 0, '#', '#'],
                ],
                'min_word_length' => 1,
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Internal Bars',
                'width' => 5,
                'height' => 5,
                'grid' => [
                    [0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0],
                ],
                'styles' => [
                    '1,1' => ['bars' => ['top', 'left']],
                    '1,3' => ['bars' => ['top', 'right']],
                    '3,1' => ['bars' => ['bottom', 'left']],
                    '3,3' => ['bars' => ['bottom', 'right']],
                ],
                'min_word_length' => 3,
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => '2',
                'width' => 15,
                'height' => 15,
                'grid' => [
                    [0, 0, 0, 0, 0, '#', 0, 0, 0, 0, '#', 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, '#', 0, 0, 0, 0, '#', 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, '#', 0, 0, 0, 0, '#', 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '#', 0, 0, 0, 0],
                    ['#', '#', '#', 0, 0, 0, 0, 0, '#', 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, '#', '#', 0, 0, 0, 0, '#', '#', '#'],
                    [0, 0, 0, '#', '#', 0, 0, 0, 0, '#', 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, '#', 0, 0, 0, 0, '#', '#', 0, 0, 0],
                    ['#', '#', '#', 0, 0, 0, 0, '#', '#', 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, '#', 0, 0, 0, 0, 0, '#', '#', '#'],
                    [0, 0, 0, 0, '#', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, '#', 0, 0, 0, 0, '#', 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, '#', 0, 0, 0, 0, '#', 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, '#', 0, 0, 0, 0, '#', 0, 0, 0, 0, 0],
                ],
                'min_word_length' => 3,
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => '3',
                'width' => 15,
                'height' => 15,
                'grid' => [
                    [0, 0, 0, 0, 0, 0, 0, '#', '#', 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, 0, '#', 0, 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, 0, '#', 0, 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, '#', 0, 0, 0, 0, 0, 0, 0, '#', 0, 0, 0],
                    [0, 0, 0, 0, '#', 0, 0, 0, 0, 0, '#', 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, '#', 0, 0, 0, '#', 0, 0, 0, 0, 0],
                    ['#', 0, 0, 0, 0, 0, '#', 0, 0, 0, 0, 0, 0, 0, '#'],
                    ['#', '#', '#', 0, 0, 0, 0, '#', 0, 0, 0, 0, '#', '#', '#'],
                    ['#', 0, 0, 0, 0, 0, 0, 0, '#', 0, 0, 0, 0, 0, '#'],
                    [0, 0, 0, 0, 0, '#', 0, 0, 0, '#', 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, '#', 0, 0, 0, 0, 0, '#', 0, 0, 0, 0],
                    [0, 0, 0, '#', 0, 0, 0, 0, 0, 0, 0, '#', 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, 0, '#', 0, 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, 0, '#', 0, 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, '#', '#', 0, 0, 0, 0, 0, 0, 0],
                ],
                'min_word_length' => 3,
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => '4',
                'width' => 15,
                'height' => 15,
                'grid' => [
                    [0, 0, 0, 0, '#', 0, 0, 0, 0, 0, '#', 0, 0, 0, 0],
                    [0, 0, 0, 0, '#', 0, 0, 0, 0, 0, '#', 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, '#', '#', 0, 0, 0, 0, 0, 0, 0, 0],
                    ['#', '#', '#', 0, 0, 0, 0, '#', 0, 0, 0, '#', '#', '#', '#'],
                    [0, 0, 0, '#', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, 0, 0, '#', '#', 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, '#', 0, 0, 0, 0, 0, '#', 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, '#', '#', 0, 0, 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '#', 0, 0, 0],
                    ['#', '#', '#', '#', 0, 0, 0, '#', 0, 0, 0, 0, '#', '#', '#'],
                    [0, 0, 0, 0, 0, 0, 0, 0, '#', '#', 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    [0, 0, 0, 0, '#', 0, 0, 0, 0, 0, '#', 0, 0, 0, 0],
                    [0, 0, 0, 0, '#', 0, 0, 0, 0, 0, '#', 0, 0, 0, 0],
                ],
                'min_word_length' => 3,
                'sort_order' => 0,
                'is_active' => true,
            ],
        ];
    }
}
