<?php

namespace App\Support;

use App\Enums\PuzzlePublication;
use Carbon\CarbonImmutable;

/**
 * A completed crossword read from a publication's feed, reduced to the
 * answer/clue pairs the clue library stores.
 */
final readonly class PublishedPuzzle
{
    /**
     * @param  list<array{answer: string, clue: string}>  $entries
     */
    public function __construct(
        public PuzzlePublication $publication,
        public CarbonImmutable $publishedOn,
        public string $title,
        public array $entries,
    ) {}
}
