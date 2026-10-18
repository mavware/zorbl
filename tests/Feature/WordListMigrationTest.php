<?php

use App\Models\Word;
use App\Services\WordListImporter;
use Illuminate\Database\Migrations\Migration;

const WORD_LIST_MIGRATION = 'migrations/2026_10_18_112617_rescore_words_from_spread_the_wordlist.php';

/**
 * The migration skips the test environment so other tests keep their own word
 * scores; run it here as if outside tests.
 */
function runWordListMigration(): void
{
    /** @var Migration $migration */
    $migration = require database_path(WORD_LIST_MIGRATION);

    app()->instance('env', 'production');

    try {
        $migration->up();
    } finally {
        app()->instance('env', 'testing');
    }
}

test('the migration is skipped in the test environment', function () {
    Word::factory()->word('WATER')->create(['score' => 61.2]);

    $this->mock(WordListImporter::class)->shouldNotReceive('import');

    $migration = require database_path(WORD_LIST_MIGRATION);
    $migration->up();

    expect(Word::where('word', 'WATER')->value('score'))->toBe(61.2);
});

test('it reloads an existing word list from the bundled file', function () {
    Word::factory()->word('WATER')->create(['score' => 61.2]);

    $this->mock(WordListImporter::class)
        ->shouldReceive('import')
        ->once()
        ->with()
        ->andReturn(['entries' => 1, 'imported' => 1, 'skipped' => 0, 'unlisted' => 0]);

    runWordListMigration();
});

test('it leaves an empty word list to the seeder', function () {
    $this->mock(WordListImporter::class)->shouldNotReceive('import');

    runWordListMigration();
});

test('the real import rescores the table when the migration runs', function () {
    Word::factory()->word('WATER')->create(['score' => 61.2]);
    Word::factory()->word('GAGEE')->create(['score' => 66.0]);
    Word::factory()->word('QZXQZX')->create(['score' => 66.0]);

    runWordListMigration();

    expect(Word::where('word', 'WATER')->value('score'))->toBe(55.0)
        ->and(Word::where('word', 'GAGEE')->value('score'))->toBe(Word::SCORE_UNVETTED)
        ->and(Word::where('word', 'PIECEOFCAKE')->value('score'))->toBe(Word::SCORE_LIVELY)
        ->and(Word::where('word', 'QZXQZX')->value('score'))->toBe(Word::SCORE_UNVETTED);
});
