<?php

use App\Enums\PuzzlePublication;
use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\User;
use App\Models\Word;
use App\Services\SpreadWordlistImporter;

/**
 * @param  list<string>  $lines
 */
function spreadWordlistFile(array $lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'spread');
    file_put_contents($path, implode("\n", $lines)."\n");

    return $path;
}

test('lines are parsed into scored grid answers', function (string $line, ?array $expected) {
    expect(SpreadWordlistImporter::parseLine($line))->toBe($expected);
})->with([
    'lively' => ['WATER;55', ['word' => 'WATER', 'length' => 5, 'score' => 55.0]],
    'lowercase' => ['water;45', ['word' => 'WATER', 'length' => 5, 'score' => 45.0]],
    'poor' => ['TAREA;20', ['word' => 'TAREA', 'length' => 5, 'score' => 20.0]],
    'unusable' => ['OOF;0', null],
    'too short' => ['AA;50', null],
    'too long' => [str_repeat('A', 22).';50', null],
    'malformed' => ['not a wordlist line', null],
]);

test('listed words take their list score and unlisted words become unvetted', function () {
    Word::factory()->word('WATER')->create(['score' => 61.2]);
    Word::factory()->word('PIECEOFCAKE')->create(['score' => 20.0]);
    Word::factory()->word('TAREA')->create(['score' => 70.4]);
    Word::factory()->word('GAGEE')->create(['score' => 66.0]);

    $result = app(SpreadWordlistImporter::class)->import(spreadWordlistFile([
        'WATER;55',
        'TAREA;20',
        'LASER;55',
        'AA;50',
        'OOF;0',
    ]));

    expect($result)->toBe(['entries' => 5, 'imported' => 3, 'skipped' => 2, 'unlisted' => 2])
        ->and(Word::where('word', 'WATER')->value('score'))->toBe(55.0)
        ->and(Word::where('word', 'TAREA')->value('score'))->toBe(20.0)
        ->and(Word::where('word', 'LASER')->first())->length->toBe(5)->score->toBe(55.0)
        ->and(Word::where('word', 'GAGEE')->value('score'))->toBe(Word::SCORE_UNVETTED)
        ->and(Word::whereIn('word', ['AA', 'OOF'])->exists())->toBeFalse();
});

test('words with a vetted source keep at least that source\'s floor', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    Word::factory()->word('ZEBRA')->create(['score' => 45.0]);
    Word::factory()->word('PACED')->create(['score' => 45.0]);
    Word::factory()->word('ONIT')->create(['score' => 90.0]);
    Word::factory()->word('OCEAN')->create(['score' => 45.0]);

    ClueEntry::create(['answer' => 'ZEBRA', 'clue' => 'Striped grazer', 'user_id' => $user->id, 'source' => PuzzlePublication::Newsday->value, 'source_date' => '2026-10-12', 'status' => ClueEntry::STATUS_PENDING]);
    ClueEntry::create(['answer' => 'PACED', 'clue' => 'Walked nervously', 'user_id' => $user->id, 'crossword_id' => $crossword->id, 'direction' => 'across', 'clue_number' => 1, 'status' => ClueEntry::STATUS_PENDING]);
    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Big blue', 'user_id' => $user->id, 'source' => PuzzlePublication::Newsday->value, 'source_date' => '2026-10-12', 'status' => ClueEntry::STATUS_PENDING]);

    app(SpreadWordlistImporter::class)->import(spreadWordlistFile(['OCEAN;55']));

    expect(Word::where('word', 'ZEBRA')->value('score'))->toBe(Word::SCORE_PUBLISHED)
        ->and(Word::where('word', 'PACED')->value('score'))->toBe(Word::SCORE_ACCEPTABLE)
        ->and(Word::where('word', 'ONIT')->value('score'))->toBe(Word::SCORE_LIVELY)
        ->and(Word::where('word', 'OCEAN')->value('score'))->toBe(55.0);
});

test('curated phrases are added when missing', function () {
    app(SpreadWordlistImporter::class)->import(spreadWordlistFile(['OCEAN;55']));

    expect(Word::where('word', 'PIECEOFCAKE')->value('score'))->toBe(Word::SCORE_LIVELY);
});

test('the command reports what it scored', function () {
    Word::factory()->word('GAGEE')->create(['score' => 66.0]);

    $this->artisan('words:import-spread-wordlist', ['--path' => spreadWordlistFile(['WATER;55', 'OOF;0'])])
        ->expectsOutputToContain('Read 2 entries: scored 1 word(s), skipped 1')
        ->expectsOutputToContain('1 word(s) not in the list are kept at the unvetted score.')
        ->assertSuccessful();
});

test('the command fails cleanly when the file is missing', function () {
    $this->artisan('words:import-spread-wordlist', ['--path' => '/nowhere/spread.txt'])
        ->expectsOutputToContain('Spread the Wordlist file not found')
        ->assertFailed();
});

test('the bundled list is in the expected format', function () {
    $path = SpreadWordlistImporter::defaultPath();

    expect(file_exists($path))->toBeTrue();

    $handle = fopen($path, 'r');
    $sample = array_filter(array_map(fn () => trim((string) fgets($handle)), range(1, 20)));
    fclose($handle);

    expect($sample)->not->toBeEmpty();

    foreach ($sample as $line) {
        expect($line)->toMatch('/^[A-Z]+;\d+$/');
    }
});
