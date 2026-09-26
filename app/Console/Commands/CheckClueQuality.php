<?php

namespace App\Console\Commands;

use App\Models\ClueEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clues:check-quality')]
#[Description('Recheck every clue library entry for quality issues')]
class CheckClueQuality extends Command
{
    public function handle(): int
    {
        $checked = 0;
        $flagged = 0;

        ClueEntry::query()->chunkById(500, function ($entries) use (&$checked, &$flagged) {
            foreach ($entries as $entry) {
                $entry->refreshQualityIssues();

                if ($entry->isDirty('quality_issues')) {
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
}
