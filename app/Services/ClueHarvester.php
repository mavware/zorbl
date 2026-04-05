<?php

namespace App\Services;

use App\Console\Commands\GenerateWordList;
use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\Word;
use Zorbl\CrosswordIO\GridNumberer;

class ClueHarvester
{
    public function __construct(private readonly GridNumberer $numberer) {}

    /**
     * Extract answer→clue pairs from a published crossword and upsert into clue_entries.
     */
    public function harvest(Crossword $crossword): void
    {
        $result = $this->numberer->number($crossword->grid, $crossword->width, $crossword->height, $crossword->styles ?? []);

        $entries = [];

        foreach (['across', 'down'] as $direction) {
            $clueList = $direction === 'across' ? $crossword->clues_across : $crossword->clues_down;
            $clueMap = collect($clueList)->keyBy('number');

            foreach ($result[$direction] as $slot) {
                $answer = $this->extractWord($crossword->solution, $slot, $direction);

                if ($answer === null) {
                    continue;
                }

                $clue = $clueMap->get($slot['number']);
                $clueText = $clue['clue'] ?? '';

                if ($clueText === '') {
                    continue;
                }

                $entries[] = [
                    'answer' => $answer,
                    'clue' => $clueText,
                    'crossword_id' => $crossword->id,
                    'user_id' => $crossword->user_id,
                    'direction' => $direction,
                    'clue_number' => $slot['number'],
                ];
            }
        }

        if (count($entries) > 0) {
            ClueEntry::upsert(
                $entries,
                ['crossword_id', 'direction', 'clue_number'],
                ['answer', 'clue', 'user_id'],
            );

            $this->syncWords($entries);
        }
    }

    /**
     * Sync harvested words into the words table with a library bonus score.
     *
     * @param  array<int, array{answer: string}>  $entries
     */
    private function syncWords(array $entries): void
    {
        $words = collect($entries)
            ->pluck('answer')
            ->unique()
            ->map(fn (string $answer) => [
                'word' => $answer,
                'length' => strlen($answer),
                'score' => GenerateWordList::calculateScore($answer) + 5.0,
            ])
            ->values()
            ->all();

        if (count($words) > 0) {
            Word::upsert($words, ['word'], ['score']);
        }
    }

    /**
     * Remove all clue entries for a crossword (e.g. when unpublishing).
     */
    public function purge(Crossword $crossword): void
    {
        $crossword->clueEntries()->delete();
    }

    /**
     * Extract the answer word from the solution grid for a given slot.
     * Returns null if any cell is empty (incomplete fill).
     */
    private function extractWord(array $solution, array $slot, string $direction): ?string
    {
        $word = '';
        $row = $slot['row'];
        $col = $slot['col'];

        for ($i = 0; $i < $slot['length']; $i++) {
            $r = $direction === 'across' ? $row : $row + $i;
            $c = $direction === 'across' ? $col + $i : $col;

            $letter = $solution[$r][$c] ?? '';

            if ($letter === '' || $letter === '#' || $letter === null) {
                return null;
            }

            $word .= $letter;
        }

        return mb_strtoupper($word);
    }
}
