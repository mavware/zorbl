<?php

use App\Models\Template;
use App\Services\ThemeWordPlacer;

/**
 * A 15x15 grid that is all blocks except a "+" — row 7 and column 7 are open.
 * This yields exactly two slots: one 15-cell across word (row 7) and one
 * 15-cell down word (column 7), crossing at cell (7, 7).
 *
 * @return array<int, array<int, int|string>>
 */
function plusGrid(): array
{
    $grid = array_fill(0, 15, array_fill(0, 15, '#'));

    for ($i = 0; $i < 15; $i++) {
        $grid[7][$i] = 0;
        $grid[$i][7] = 0;
    }

    return $grid;
}

test('fits a single word into an open template', function (): void {
    Template::factory()->square(15)->create();

    $word = str_repeat('A', 15);

    $result = app(ThemeWordPlacer::class)->place([$word]);

    expect($result)->not->toBeNull();

    // The word should occupy one full across row of the returned solution.
    $rows = array_map(fn (array $row): string => implode('', $row), $result['solution']);
    expect($rows)->toContain($word);
});

test('places two words that intersect on a matching letter', function (): void {
    Template::factory()->square(15)->state(['grid' => plusGrid()])->create();

    $across = str_repeat('A', 15);
    $down = str_repeat('B', 7).'A'.str_repeat('B', 7); // 'A' at index 7 matches the across word

    $result = app(ThemeWordPlacer::class)->place([$across, $down]);

    expect($result)->not->toBeNull()
        ->and($result['placements'])->toHaveCount(2)
        ->and($result['solution'][7][7])->toBe('A');
});

test('rejects two words that would collide at the intersection', function (): void {
    Template::factory()->square(15)->state(['grid' => plusGrid()])->create();

    $across = str_repeat('A', 15);
    $down = str_repeat('B', 15); // 'B' at index 7 conflicts with the across word's 'A'

    $result = app(ThemeWordPlacer::class)->place([$across, $down]);

    expect($result)->toBeNull();
});

test('returns null when no slot matches a word length', function (): void {
    Template::factory()->square(15)->create(); // open grid only has 15-length slots

    $result = app(ThemeWordPlacer::class)->place(['SHORT']);

    expect($result)->toBeNull();
});

test('ignores inactive and wrong-sized templates', function (): void {
    Template::factory()->square(15)->inactive()->create();
    Template::factory()->square(13)->create();

    $result = app(ThemeWordPlacer::class)->place([str_repeat('A', 15)]);

    expect($result)->toBeNull();
});

test('normalizes phrases to letters before placing', function (): void {
    Template::factory()->square(15)->create();

    // "PIECE OF CAKE!!" -> "PIECEOFCAKE" is 11 letters, not 15, so it should not fit
    // an open grid; but a 15-letter phrase with spaces should.
    $result = app(ThemeWordPlacer::class)->place(['A B C D E F G H I J K L M N O']);

    expect($result)->not->toBeNull();
    $rows = array_map(fn (array $row): string => implode('', $row), $result['solution']);
    expect($rows)->toContain('ABCDEFGHIJKLMNO');
});
