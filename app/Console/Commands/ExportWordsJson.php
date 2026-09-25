<?php

namespace App\Console\Commands;

use App\Services\WordExporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('words:export-json {--force : Rewrite every shard even when nothing has changed}')]
#[Description('Export all words, scores, and approved clues as static JSON shards (by word length) to the configured disk')]
class ExportWordsJson extends Command
{
    public function handle(WordExporter $exporter): int
    {
        $result = $exporter->export(force: (bool) $this->option('force'));

        if ($result['skipped']) {
            $this->info("Word export is up to date ({$result['shards']} shards, {$result['words']} words, {$result['clues']} clues). Nothing written.");

            return self::SUCCESS;
        }

        $this->info("Exported {$result['words']} words and {$result['clues']} approved clues across {$result['shards']} shards.");
        $this->line('Manifest: '.$exporter->manifestUrl());

        return self::SUCCESS;
    }
}
