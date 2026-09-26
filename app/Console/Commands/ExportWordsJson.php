<?php

namespace App\Console\Commands;

use App\Services\WordExporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('words:export-json')]
#[Description('Build the word list JSON (words, scores, and approved clues, sharded by word length) and warm the cache the API serves it from')]
class ExportWordsJson extends Command
{
    public function handle(WordExporter $exporter): int
    {
        $exporter->forgetFingerprint();
        $exporter->releaseBuildLock();
        $result = $exporter->build(force: true);

        $this->info('Cached '.$result['words'].' words and '.$result['clues'].' approved clues across '.$result['shards'].' shards.');
        $this->line('Manifest: '.route('api.v1.words.manifest'));

        return self::SUCCESS;
    }
}
