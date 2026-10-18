<?php

namespace App\Services;

use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\Word;
use CrosswordBuilder\CrosswordIO\GridNumberer;

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
                    'quality_issues' => ClueEntry::encodedQualityIssues($clueText, $answer),
                    'crossword_id' => $crossword->id,
                    'user_id' => $crossword->user_id,
                    'direction' => $direction,
                    'clue_number' => $slot['number'],
                    'status' => ClueEntry::STATUS_PENDING,
                ];
            }
        }

        if (count($entries) > 0) {
            ClueEntry::upsert(
                $entries,
                ['crossword_id', 'direction', 'clue_number'],
                ['answer', 'clue', 'quality_issues', 'user_id'],
            );

            $this->syncWords($entries);
        }
    }

    /**
     * Sync harvested words into the words table. A constructor publishing an
     * answer vouches for it as acceptable fill, so new words start at that
     * floor and known words are raised to it; better scores are left alone.
     *
     * @param  array<int, array{answer: string}>  $entries
     */
    private function syncWords(array $entries): void
    {
        $answers = collect($entries)->pluck('answer')->unique()->values();

        if ($answers->isEmpty()) {
            return;
        }

        $now = now();

        Word::insertOrIgnore($answers->map(fn (string $answer): array => [
            'word' => $answer,
            'length' => strlen($answer),
            'score' => Word::SCORE_ACCEPTABLE,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        Word::query()
            ->whereIn('word', $answers->all())
            ->where('score', '<', Word::SCORE_ACCEPTABLE)
            ->update(['score' => Word::SCORE_ACCEPTABLE]);
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
