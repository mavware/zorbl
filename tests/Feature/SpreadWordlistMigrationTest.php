<?php

use App\Models\Word;
use App\Services\SpreadWordlistImporter;
use Illuminate\Database\Migrations\Migration;

const SPREAD_WORDLIST_MIGRATION = 'migrations/2026_10_18_112617_rescore_words_from_spread_the_wordlist.php';

/**
 * The migration skips the test environment so other tests keep their own word
 * scores; run it here as if outside tests.
 */
function runSpreadWordlistMigration(): void
{
    /** @var Migration $migration */
    $migration = require database_path(SPREAD_WORDLIST_MIGRATION);

    app()->instance('env', 'production');

    try {
        $migration->up();
    } finally {
        app()->instance('env', 'testing');
    }
}

test('the migration is skipped in the test environment', function () {
    Word::factory()->word('WATER')->create(['score' => 61.2]);

    $this->mock(SpreadWordlistImporter::class)->shouldNotReceive('import');

    $migration = require database_path(SPREAD_WORDLIST_MIGRATION);
    $migration->up();

    expect(Word::where('word', 'WATER')->value('score'))->toBe(61.2);
});

test('it rescores an existing word list from the bundled file', function () {
    Word::factory()->word('WATER')->create(['score' => 61.2]);

    $this->mock(SpreadWordlistImporter::class)
        ->shouldReceive('import')
        ->once()
        ->with()
        ->andReturn(['entries' => 1, 'imported' => 1, 'skipped' => 0, 'unlisted' => 0]);

    runSpreadWordlistMigration();
});

test('it leaves an empty word list to the seeder', function () {
    $this->mock(SpreadWordlistImporter::class)->shouldNotReceive('import');

    runSpreadWordlistMigration();
});

test('the real import rescores the table when the migration runs', function () {
    Word::factory()->word('WATER')->create(['score' => 61.2]);
    Word::factory()->word('GAGEE')->create(['score' => 66.0]);

    runSpreadWordlistMigration();

    expect(Word::where('word', 'WATER')->value('score'))->toBe(55.0)
        ->and(Word::where('word', 'GAGEE')->value('score'))->toBe(Word::SCORE_UNVETTED);
});
