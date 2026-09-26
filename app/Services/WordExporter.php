<?php

namespace App\Services;

use App\Models\ClueEntry;
use App\Models\Word;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds the public word list (with scores and approved clues) as JSON, one
 * shard per word length plus a manifest, and keeps it in the cache.
 *
 * Served by the API:
 *   /api/v1/words/manifest    index of shards, counts, and a change fingerprint
 *   /api/v1/words/{length}    every word of that length with its score and clues
 *
 * Cache keys include the fingerprint, so any change to words or approved clues
 * makes the next request rebuild, and clients can skip shards whose hash they
 * already hold.
 */
class WordExporter
{
    public const int VERSION = 2;

    /**
     * How long the fingerprint is reused before the database is checked for changes.
     */
    public const int FINGERPRINT_SECONDS = 600;

    /**
     * How long a built export stays cached. Changes invalidate it sooner via the fingerprint.
     */
    public const int EXPORT_SECONDS = 604800;

    /**
     * The manifest JSON, building the export if it isn't cached.
     */
    public function manifest(): string
    {
        $key = $this->cacheKey('manifest');

        return Cache::get($key) ?? $this->buildThenGet($key) ?? '';
    }

    /**
     * The JSON shard for one word length, or null when no word has that length.
     */
    public function shard(int $length): ?string
    {
        $key = $this->cacheKey("shard:{$length}");

        return Cache::get($key) ?? $this->buildThenGet($key);
    }

    /**
     * Build every shard and the manifest and store them in the cache.
     *
     * Shards are built one length at a time from plain rows and cached as soon
     * as they're encoded, so memory stays bounded by the largest single shard
     * rather than the whole word list.
     *
     * @return array{shards: int, words: int, clues: int}
     */
    public function build(bool $force = false): array
    {
        $fingerprint = $this->fingerprint();

        return Cache::lock("word-export:build:{$fingerprint}", 300)->block(120, function () use ($fingerprint, $force): array {
            $manifestKey = $this->cacheKey('manifest', $fingerprint);

            // Another request may have finished the build while this one waited on the lock.
            if (! $force && ($cached = Cache::get($manifestKey)) !== null) {
                $manifest = json_decode($cached, true, 512, JSON_THROW_ON_ERROR);

                return ['shards' => count($manifest['shards']), 'words' => $manifest['words'], 'clues' => $manifest['clues']];
            }

            $generatedAt = now()->toIso8601String();
            $shardSummaries = [];
            $totalWords = 0;
            $totalClues = 0;

            foreach ($this->lengths() as $length) {
                ['json' => $wordsJson, 'words' => $wordCount, 'clues' => $clueCount] = $this->wordsForLength($length);
                $json = '{"version":'.self::VERSION
                    .',"generated_at":'.$this->encode($generatedAt)
                    .',"length":'.$length
                    .',"words":['.$wordsJson.']}';
                unset($wordsJson);

                Cache::put($this->cacheKey("shard:{$length}", $fingerprint), $json, self::EXPORT_SECONDS);

                $shardSummaries[] = [
                    'length' => $length,
                    'url' => route('api.v1.words.shard', $length),
                    'words' => $wordCount,
                    'clues' => $clueCount,
                    'bytes' => strlen($json),
                    'sha256' => hash('sha256', $json),
                ];
                $totalWords += $wordCount;
                $totalClues += $clueCount;
                unset($json);
            }

            Cache::put($manifestKey, $this->encode([
                'version' => self::VERSION,
                'generated_at' => $generatedAt,
                'fingerprint' => $fingerprint,
                'words' => $totalWords,
                'clues' => $totalClues,
                'shards' => $shardSummaries,
            ]), self::EXPORT_SECONDS);

            return ['shards' => count($shardSummaries), 'words' => $totalWords, 'clues' => $totalClues];
        });
    }

    /**
     * Release a build lock left behind by a build that crashed (e.g. ran out of
     * memory) so a forced rebuild doesn't wait for it to expire.
     */
    public function releaseBuildLock(): void
    {
        Cache::lock('word-export:build:'.$this->fingerprint())->forceRelease();
    }

    private function buildThenGet(string $key): ?string
    {
        $this->build();

        return Cache::get($key);
    }

    /**
     * A cheap digest of everything the export depends on. Any word or approved
     * clue being added, removed, rescored, or re-reviewed changes it. Reused for
     * a few minutes so every request doesn't query the word table.
     */
    public function fingerprint(): string
    {
        return Cache::remember('word-export:fingerprint', self::FINGERPRINT_SECONDS, fn (): string => hash('sha256', implode('|', [
            self::VERSION,
            Word::query()->count(),
            (string) Word::query()->max('updated_at'),
            ClueEntry::approved()->count(),
            (string) ClueEntry::approved()->max('updated_at'),
        ])));
    }

    /**
     * Drop the cached fingerprint so the next request checks the database for changes.
     */
    public function forgetFingerprint(): void
    {
        Cache::forget('word-export:fingerprint');
    }

    private function cacheKey(string $suffix, ?string $fingerprint = null): string
    {
        return 'word-export:'.($fingerprint ?? $this->fingerprint()).':'.$suffix;
    }

    /**
     * Every length that has a word or an approved clue.
     *
     * @return list<int>
     */
    private function lengths(): array
    {
        return Word::query()->distinct()->pluck('length')
            ->merge($this->approvedClues()->selectRaw('DISTINCT LENGTH(clue_entries.answer) AS answer_length')->pluck('answer_length'))
            ->map(fn (int|string $length): int => (int) $length)
            ->filter(fn (int $length): bool => $length > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Words of one length merged with their approved clues, as the comma-joined
     * JSON objects of the shard's "words" array. Answers that only exist as
     * approved clues (not yet in the word list) are included with a null score
     * so no approved clue is left out of the export.
     *
     * Each word is encoded as soon as it's read: nested PHP arrays for a large
     * shard take roughly ten times the memory of the JSON they produce.
     *
     * @return array{json: string, words: int, clues: int}
     */
    private function wordsForLength(int $length): array
    {
        $cluesByAnswer = $this->approvedCluesForLength($length);
        $encodedByWord = [];
        $clueCount = 0;

        $rows = Word::query()->toBase()->where('length', $length)->select(['word', 'score'])->cursor();

        foreach ($rows as $row) {
            $encodedByWord[$row->word] = $this->encodeWord($row->word, round((float) $row->score, 2), $cluesByAnswer[$row->word] ?? null);
            $clueCount += $cluesByAnswer[$row->word]['count'] ?? 0;
            unset($cluesByAnswer[$row->word]);
        }

        foreach ($cluesByAnswer as $answer => $clues) {
            $encodedByWord[(string) $answer] = $this->encodeWord((string) $answer, null, $clues);
            $clueCount += $clues['count'];
        }

        ksort($encodedByWord, SORT_STRING);

        return ['json' => implode(',', $encodedByWord), 'words' => count($encodedByWord), 'clues' => $clueCount];
    }

    /**
     * @param  array{count: int, json: string}|null  $clues
     */
    private function encodeWord(string $word, ?float $score, ?array $clues): string
    {
        return '{"word":'.$this->encode($word)
            .',"score":'.$this->encode($score)
            .',"clues":['.($clues['json'] ?? '').']}';
    }

    /**
     * Approved clues for answers of one length, grouped by answer in the order
     * they were written, each group already encoded as comma-joined JSON objects.
     *
     * @return array<string, array{count: int, json: string}>
     */
    private function approvedCluesForLength(int $length): array
    {
        $cluesByAnswer = [];

        $rows = $this->approvedClues()
            ->leftJoin('crosswords', 'crosswords.id', '=', 'clue_entries.crossword_id')
            ->whereRaw('LENGTH(clue_entries.answer) = ?', [$length])
            ->orderBy('clue_entries.id')
            ->select(['clue_entries.answer', 'clue_entries.clue', 'crosswords.id as puzzle_id', 'crosswords.title as puzzle_title', 'crosswords.author as puzzle_author'])
            ->cursor();

        foreach ($rows as $row) {
            $clue = $this->encode([
                'text' => $row->clue,
                'puzzle' => $row->puzzle_id === null ? null : [
                    'title' => $row->puzzle_title,
                    'author' => $row->puzzle_author,
                ],
            ]);

            if (isset($cluesByAnswer[$row->answer])) {
                $cluesByAnswer[$row->answer]['count']++;
                $cluesByAnswer[$row->answer]['json'] .= ','.$clue;
            } else {
                $cluesByAnswer[$row->answer] = ['count' => 1, 'json' => $clue];
            }
        }

        return $cluesByAnswer;
    }

    private function approvedClues(): Builder
    {
        return DB::table('clue_entries')->where('clue_entries.status', ClueEntry::STATUS_APPROVED);
    }

    private function encode(mixed $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
