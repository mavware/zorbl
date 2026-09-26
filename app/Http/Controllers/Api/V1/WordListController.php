<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WordExporter;
use Illuminate\Http\Response;

/**
 * @tags Word List
 */
class WordListController extends Controller
{
    /**
     * Index of the word list shards, with counts, hashes, and a change fingerprint.
     */
    public function manifest(WordExporter $exporter): Response
    {
        return $this->json($exporter->manifest());
    }

    /**
     * Every word of one length with its score and approved clues.
     */
    public function shard(WordExporter $exporter, int $length): Response
    {
        $json = $exporter->shard($length);

        abort_if($json === null, 404);

        return $this->json($json);
    }

    private function json(string $json): Response
    {
        return response($json)->header('Content-Type', 'application/json');
    }
}
