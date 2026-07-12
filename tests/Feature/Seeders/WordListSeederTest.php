<?php

use App\Console\Commands\GenerateWordList;
use App\Models\Word;
use Database\Seeders\WordListSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(RefreshDatabase::class);

/**
 * Invoke the seeder's private phrase-loading step in isolation so we don't have
 * to seed the full 210k-word base list to assert phrase behavior.
 */
function seedPhrases(): void
{
    $command = new Command;
    $command->setOutput(new OutputStyle(
        new ArrayInput([]),
        new BufferedOutput,
    ));

    $seeder = new WordListSeeder;
    $seeder->setCommand($command);

    $method = new ReflectionMethod(WordListSeeder::class, 'seedPhrases');
    $method->invoke($seeder, now());
}

it('normalizes short-word combos to letters only', function () {
    seedPhrases();

    // "on it" -> ONIT, "it's a deal" -> ITSADEAL
    expect(Word::where('word', 'ONIT')->exists())->toBeTrue();
    expect(Word::where('word', 'ITSADEAL')->exists())->toBeTrue();

    $onit = Word::where('word', 'ONIT')->firstOrFail();
    expect($onit->length)->toBe(4);
});

it('seeds idioms and sayings', function () {
    seedPhrases();

    foreach (['PIECEOFCAKE', 'BREAKALEG', 'HITTHEROAD', 'BITETHEBULLET'] as $phrase) {
        expect(Word::where('word', $phrase)->exists())->toBeTrue();
    }
});

it('gives phrases a liveliness score bonus over their base score', function () {
    seedPhrases();

    $onit = Word::where('word', 'ONIT')->firstOrFail();
    $base = GenerateWordList::calculateScore('ONIT');

    expect($onit->score)->toBeGreaterThan($base);
    expect($onit->score)->toBe(round($base + 25.0, 2));
});

it('is idempotent when run multiple times', function () {
    seedPhrases();
    $count = Word::count();

    seedPhrases();

    expect(Word::count())->toBe($count);
});
