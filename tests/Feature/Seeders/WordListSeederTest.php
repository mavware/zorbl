<?php

use App\Models\Word;
use App\Services\WordListImporter;
use Database\Seeders\WordListSeeder;

test('the seeder loads the words table through the importer and reports what it did', function () {
    $this->mock(WordListImporter::class)
        ->shouldReceive('import')
        ->once()
        ->with()
        ->andReturn(['entries' => 5, 'imported' => 3, 'skipped' => 2, 'unlisted' => 1]);

    $this->artisan('db:seed', ['--class' => WordListSeeder::class, '--no-interaction' => true])
        ->expectsOutputToContain('Read 5 entries: scored 3 word(s), skipped 2')
        ->expectsOutputToContain('1 word(s) not in the list are kept at the unvetted score.')
        ->assertSuccessful();
});

test('seed:words runs the seeder', function () {
    $this->mock(WordListImporter::class)
        ->shouldReceive('import')
        ->once()
        ->andReturn(['entries' => 1, 'imported' => 1, 'skipped' => 0, 'unlisted' => 0]);

    $this->artisan('seed:words')
        ->expectsOutputToContain('Read 1 entries: scored 1 word(s), skipped 0')
        ->assertSuccessful();
});

test('seed:words runs in production without asking for confirmation', function () {
    $this->mock(WordListImporter::class)
        ->shouldReceive('import')
        ->once()
        ->andReturn(['entries' => 1, 'imported' => 1, 'skipped' => 0, 'unlisted' => 0]);

    app()->instance('env', 'production');

    try {
        $this->artisan('seed:words')
            ->doesntExpectOutputToContain('Command cancelled.')
            ->expectsOutputToContain('Read 1 entries: scored 1 word(s), skipped 0')
            ->assertSuccessful();
    } finally {
        app()->instance('env', 'testing');
    }
});

test('a missing word list is reported without touching the table', function () {
    Word::factory()->word('WATER')->create(['score' => 55.0]);

    $this->mock(WordListImporter::class)
        ->shouldReceive('import')
        ->once()
        ->andThrow(new InvalidArgumentException('Word list file not found: /nowhere/wordlist.txt'));

    $this->artisan('db:seed', ['--class' => WordListSeeder::class, '--no-interaction' => true])
        ->expectsOutputToContain('Word list file not found: /nowhere/wordlist.txt');

    expect(Word::where('word', 'WATER')->value('score'))->toBe(55.0);
});
