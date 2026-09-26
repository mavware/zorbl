<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns a word's English Wiktionary definitions (CC BY-SA) into
 * definition-style clues, as a free alternative to the AI clue writer.
 *
 * Words are stored without spaces, so multi-word phrases ("HOTDOG" for
 * "hot dog") usually have no matching entry and are reported as a failure.
 */
class WiktionaryClueWriter
{
    public const string DEFINITION_URL = 'https://en.wiktionary.org/api/rest_v1/page/definition/';

    /**
     * Longer definitions read as explanations rather than clues.
     */
    public const int MAX_CLUE_LENGTH = 100;

    /**
     * @return array{success: bool, clues: list<string>, message: string}
     */
    public function write(string $word, int $limit): array
    {
        $word = strtoupper(trim($word));

        if ($word === '' || $limit < 1) {
            return ['success' => true, 'clues' => [], 'message' => 'Nothing to write.'];
        }

        try {
            $response = Http::withUserAgent(config('app.name').'/1.0 ('.config('app.url').')')
                ->timeout(15)
                ->get(self::DEFINITION_URL.rawurlencode(strtolower($word)));
        } catch (ConnectionException $e) {
            Log::warning('Wiktionary clue lookup failed', ['word' => $word, 'error' => $e->getMessage()]);

            return $this->failure('Failed to connect to Wiktionary: '.$e->getMessage());
        }

        if ($response->notFound()) {
            return $this->failure('No Wiktionary entry.');
        }

        if ($response->failed()) {
            Log::warning('Wiktionary clue lookup error', ['word' => $word, 'status' => $response->status()]);

            return $this->failure('Wiktionary returned an error.');
        }

        $definitions = collect($response->json('en') ?? [])
            ->flatMap(fn (array $entry): array => array_column($entry['definitions'] ?? [], 'definition'))
            ->map(fn (mixed $definition): ?string => is_string($definition) ? self::toClue($definition) : null)
            ->filter()
            ->all();

        $clues = AiWordClueWriter::sanitize($definitions, $word, $limit);

        if ($clues === []) {
            return $this->failure('Wiktionary had no usable English definitions.');
        }

        return ['success' => true, 'clues' => $clues, 'message' => 'Found '.count($clues).' clue(s).'];
    }

    /**
     * Clean one definition into a clue: strip markup, leading labels such as
     * "(transitive)" or sense-group headings ("Terms relating to animals."),
     * and the trailing period. Returns null for inflection and
     * spelling-variant entries ("Plural of ...", "Alternative form of ..."),
     * which only point at another word, and for over-long definitions.
     */
    public static function toClue(string $definition): ?string
    {
        $text = html_entity_decode(strip_tags($definition), ENT_QUOTES | ENT_HTML5);
        $text = trim(preg_replace(['/\s+/', '/\s+([,;:])/'], [' ', '$1'], $text) ?? '');
        $text = trim(preg_replace('/^(\([^)]*\)\s*)+/', '', $text) ?? '');
        $text = trim(preg_replace('/^Terms relating to [^.]*\.\s*/i', '', $text) ?? '');
        $text = rtrim($text, '. ');

        if ($text === '' || mb_strlen($text) > self::MAX_CLUE_LENGTH) {
            return null;
        }

        if (preg_match('/^(\S+\s+){0,4}(form|spelling|plural|participle|tense|comparative|superlative|abbreviation|initialism|acronym|misspelling|synonym)\s+of\b/i', $text)) {
            return null;
        }

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /**
     * @return array{success: false, clues: list<string>, message: string}
     */
    private function failure(string $message): array
    {
        return ['success' => false, 'clues' => [], 'message' => $message];
    }
}
