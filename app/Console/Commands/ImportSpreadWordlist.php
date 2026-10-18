<?php

namespace App\Console\Commands;

use App\Services\SpreadWordlistImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('words:import-spread-wordlist
    {--path= : Spread the Wordlist file in WORD;score format (defaults to database/data/spreadthewordlist.txt)}')]
#[Description('Rescore the word list from Spread the Wordlist; words it does not list are kept but scored as unvetted')]
class ImportSpreadWordlist extends Command
{
    public function handle(SpreadWordlistImporter $importer): int
    {
        $path = $this->option('path');

        try {
            $result = $importer->import($path === null ? null : (string) $path);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Read {$result['entries']} entries: scored {$result['imported']} word(s), skipped {$result['skipped']} (unusable or outside 3-21 letters).");
        $this->line("{$result['unlisted']} word(s) not in the list are kept at the unvetted score.");

        return self::SUCCESS;
    }
}
