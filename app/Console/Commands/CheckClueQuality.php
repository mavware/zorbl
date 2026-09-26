<?php

namespace App\Console\Commands;

use App\Models\ClueEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('clues:check-quality {--dry-run : Report what would change without deleting or saving anything}')]
#[Description('Delete duplicate clue library entries and recheck the rest for quality issues')]
class CheckClueQuality extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $duplicateIds = $this->duplicateIds();

        if ($dryRun) {
            $this->info("Would delete {$duplicateIds->count()} duplicate clues.");
        } else {
            $duplicateIds->chunk(500)->each(fn (Collection $ids) => ClueEntry::whereKey($ids->all())->delete());
            $this->info("Deleted {$duplicateIds->count()} duplicate clues.");
        }

        $checked = 0;
        $flagged = 0;

        ClueEntry::query()->chunkById(500, function ($entries) use (&$checked, &$flagged, $dryRun) {
            foreach ($entries as $entry) {
                $entry->refreshQualityIssues();

                if (! $dryRun && $entry->isDirty('quality_issues')) {
                    $entry->timestamps = false;
                    $entry->saveQuietly();
                }

                $checked++;
                $flagged += $entry->quality_issues === null ? 0 : 1;
            }
        });

        $this->info("Checked {$checked} clues; {$flagged} have quality issues.");

        return self::SUCCESS;
    }

    /**
     * IDs of every entry that repeats another entry's answer and clue,
     * ignoring case and surrounding whitespace. Each group keeps one entry:
     * an approved one if there is one, otherwise the oldest.
     *
     * @return Collection<int, int>
     */
    private function duplicateIds(): Collection
    {
        return ClueEntry::query()
            ->selectRaw('answer, LOWER(TRIM(clue)) as normalized_clue')
            ->groupByRaw('answer, LOWER(TRIM(clue))')
            ->havingRaw('COUNT(*) > 1')
            ->toBase()
            ->get()
            ->flatMap(fn (object $group): array => array_slice(
                ClueEntry::query()
                    ->where('answer', $group->answer)
                    ->whereRaw('LOWER(TRIM(clue)) = ?', [$group->normalized_clue])
                    ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ClueEntry::STATUS_APPROVED])
                    ->orderBy('id')
                    ->pluck('id')
                    ->all(),
                1,
            ));
    }
}
