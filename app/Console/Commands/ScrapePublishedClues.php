<?php

namespace App\Console\Commands;

use App\Enums\PuzzlePublication;
use App\Services\PublishedClueScraper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

#[Signature('clues:scrape-published
    {--publication= : Only read this publication: "newsday" or "guardian-quick" (default: all of them)}
    {--puzzles=3 : How many puzzles to read per publication this run}
    {--approve : Store the clues as approved instead of pending review}
    {--restart : Forget the saved position and start again from the latest puzzle}')]
#[Description('Add words and clues from completed crosswords in trusted publications')]
class ScrapePublishedClues extends Command
{
    public function handle(PublishedClueScraper $scraper): int
    {
        $puzzles = max(1, (int) $this->option('puzzles'));
        $approve = (bool) $this->option('approve');
        $publications = $this->publications();

        if ($publications === null) {
            $this->error('Unknown --publication. Use one of: '.implode(', ', array_column(PuzzlePublication::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($publications as $publication) {
            if ($this->option('restart')) {
                $scraper->restart($publication);
            }

            try {
                $result = $scraper->scrape($publication, $puzzles, $approve);
            } catch (ConnectionException|RequestException $e) {
                $failed++;
                $this->error("{$publication->label()}: could not read the feed: {$e->getMessage()}");

                continue;
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $status = $approve ? 'approved' : 'pending review';
            $this->info("{$publication->label()}: read {$result['fetched']} puzzle(s), added {$result['inserted']} clue(s) {$status}, skipped {$result['skipped']}.");

            if ($result['caught_up']) {
                $this->line("{$publication->label()}: caught up; the next puzzle isn't out yet.");
            }
        }

        return $failed === count($publications) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<PuzzlePublication>|null
     */
    private function publications(): ?array
    {
        $option = $this->option('publication');

        if ($option === null || $option === '') {
            return PuzzlePublication::cases();
        }

        $publication = PuzzlePublication::tryFrom((string) $option);

        return $publication === null ? null : [$publication];
    }
}
