<?php

namespace App\Console\Commands;

use App\Services\WiktionaryWordImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

#[Signature('words:import-wiktionary
    {--pages=10 : How many 500-entry Wiktionary pages to read this run}
    {--category=English lemmas : Wiktionary category to walk, e.g. "English multiword terms"}
    {--restart : Start from the beginning of the category instead of where the last run stopped}')]
#[Description('Add words and phrases from Wiktionary that are not in the word list yet')]
class ImportWiktionaryWords extends Command
{
    public function handle(WiktionaryWordImporter $importer): int
    {
        $pages = max(1, (int) $this->option('pages'));
        $category = (string) $this->option('category');

        if ($this->option('restart')) {
            $importer->restart($category);
        }

        try {
            $result = $importer->import($pages, $category);
        } catch (ConnectionException|RequestException $e) {
            $this->error('Could not read Wiktionary: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Read {$result['pages']} page(s), {$result['scanned']} entries: added {$result['added']} new word(s).");

        if ($result['finished']) {
            $this->line("Reached the end of \"{$category}\"; the next run starts from the beginning.");
        }

        return self::SUCCESS;
    }
}
