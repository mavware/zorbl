<?php

namespace App\Console\Commands;

use App\Enums\ClueSource;
use App\Services\WordClueBackfiller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('clues:backfill
    {--words=1 : How many clue-less words to process this run}
    {--limit=20 : Maximum clues to write per word}
    {--approve : Store the clues as approved instead of pending review}
    {--source=ai : Where clues come from: "ai" (Claude, spends API credits) or "wiktionary" (free dictionary definitions)}')]
#[Description('Pick catalog words with no clues and write clues for them with AI or from Wiktionary definitions')]
class BackfillWordClues extends Command
{
    public function handle(WordClueBackfiller $backfiller): int
    {
        $count = max(1, (int) $this->option('words'));
        $limit = max(1, (int) $this->option('limit'));
        $approve = (bool) $this->option('approve');
        $source = ClueSource::tryFrom((string) $this->option('source'));

        if ($source === null) {
            $this->error('Unknown --source. Use one of: '.implode(', ', array_column(ClueSource::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $words = $backfiller->candidates($count);

        if ($words->isEmpty()) {
            $this->info('Every catalog word already has at least one clue. Nothing to do.');

            return self::SUCCESS;
        }

        $inserted = 0;
        $failed = 0;

        foreach ($words as $word) {
            try {
                $result = $backfiller->backfill($word, $limit, $approve, $source);
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            if ($result['success']) {
                $inserted += $result['inserted'];
                $this->line("{$word->word}: wrote {$result['inserted']} clue(s).");
            } else {
                $failed++;
                $this->warn("{$word->word}: {$result['message']}");
            }
        }

        $status = $approve ? 'approved' : 'pending review';
        $this->info("Backfilled {$inserted} clue(s) for {$words->count()} word(s), {$status}. {$failed} failed.");

        return $failed === $words->count() ? self::FAILURE : self::SUCCESS;
    }
}
