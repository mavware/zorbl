<?php

use App\Enums\PuzzlePublication;
use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\User;
use App\Models\Word;
use App\Services\WordListImporter;

/**
 * @param  list<string>  $lines
 */
function wordListFile(array $lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'wordlist');
    file_put_contents($path, implode("\n", $lines)."\n");

    return $path;
}

test('lines are parsed into scored grid answers', function (string $line, ?array $expected) {
    expect(WordListImporter::parseLine($line))->toBe($expected);
})->with([
    'lively' => ["WATER\t55", ['word' => 'WATER', 'length' => 5, 'score' => 55.0]],
    'lowercase' => ["water\t45", ['word' => 'WATER', 'length' => 5, 'score' => 45.0]],
    'poor' => ["TAREA\t20", ['word' => 'TAREA', 'length' => 5, 'score' => 20.0]],
    'decimal' => ["TAREA\t12.5", ['word' => 'TAREA', 'length' => 5, 'score' => 12.5]],
    'unusable' => ["OOF\t0", null],
    'too short' => ["AA\t50", null],
    'too long' => [str_repeat('A', 22)."\t50", null],
    'semicolon separated' => ['WATER;55', null],
    'malformed' => ['not a wordlist line', null],
]);

test('listed words take their list score and unlisted words become unvetted', function () {
    Word::factory()->word('WATER')->create(['score' => 61.2]);
    Word::factory()->word('TAREA')->create(['score' => 70.4]);
    Word::factory()->word('GAGEE')->create(['score' => 66.0]);

    $result = app(WordListImporter::class)->import(wordListFile([
        "WATER\t55",
        "TAREA\t20",
        "LASER\t55",
        "AA\t50",
        "OOF\t0",
    ]));

    expect($result)->toBe(['entries' => 5, 'imported' => 3, 'skipped' => 2, 'unlisted' => 1])
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
    Word::factory()->word('OCEAN')->create(['score' => 45.0]);

    ClueEntry::create(['answer' => 'ZEBRA', 'clue' => 'Striped grazer', 'user_id' => $user->id, 'source' => PuzzlePublication::Newsday->value, 'source_date' => '2026-10-12', 'status' => ClueEntry::STATUS_PENDING]);
    ClueEntry::create(['answer' => 'PACED', 'clue' => 'Walked nervously', 'user_id' => $user->id, 'crossword_id' => $crossword->id, 'direction' => 'across', 'clue_number' => 1, 'status' => ClueEntry::STATUS_PENDING]);
    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Big blue', 'user_id' => $user->id, 'source' => PuzzlePublication::Newsday->value, 'source_date' => '2026-10-12', 'status' => ClueEntry::STATUS_PENDING]);

    app(WordListImporter::class)->import(wordListFile(["OCEAN\t55"]));

    expect(Word::where('word', 'ZEBRA')->value('score'))->toBe(Word::SCORE_PUBLISHED)
        ->and(Word::where('word', 'PACED')->value('score'))->toBe(Word::SCORE_ACCEPTABLE)
        ->and(Word::where('word', 'OCEAN')->value('score'))->toBe(55.0);
});

test('a missing file is reported rather than emptying the table', function () {
    Word::factory()->word('WATER')->create(['score' => 55.0]);

    expect(fn () => app(WordListImporter::class)->import('/nowhere/wordlist.txt'))
        ->toThrow(InvalidArgumentException::class, 'Word list file not found')
        ->and(Word::where('word', 'WATER')->value('score'))->toBe(55.0);
});

test('the bundled list is in the expected format', function () {
    $path = WordListImporter::defaultPath();

    expect(file_exists($path))->toBeTrue();

    $handle = fopen($path, 'r');
    $sample = array_filter(array_map(fn () => trim((string) fgets($handle)), range(1, 20)));
    fclose($handle);

    expect($sample)->not->toBeEmpty();

    foreach ($sample as $line) {
        expect($line)->toMatch('/^[A-Z]+\t\d+$/');
    }
});

test('the bundled list folds every source into one scored dictionary', function () {
    $scores = [];

    foreach (file(WordListImporter::defaultPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        [$word, $score] = explode("\t", $line);

        if (in_array($word, ['WATER', 'TAREA', 'GAGEE', 'PIECEOFCAKE', 'ONIT'], true)) {
            $scores[$word] = (float) $score;
        }
    }

    // Spread the Wordlist's own scores, a dictionary word it doesn't know, and curated phrases.
    expect($scores)->toBe([
        'GAGEE' => Word::SCORE_UNVETTED,
        'ONIT' => Word::SCORE_LIVELY,
        'PIECEOFCAKE' => Word::SCORE_LIVELY,
        'TAREA' => 20.0,
        'WATER' => 55.0,
    ]);
});
