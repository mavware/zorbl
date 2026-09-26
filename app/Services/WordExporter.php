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
                $words = $this->wordsForLength($length);
                $wordCount = count($words);
                $clueCount = array_sum(array_map(fn (array $word): int => count($word['clues']), $words));
                $json = $this->encode([
                    'version' => self::VERSION,
                    'generated_at' => $generatedAt,
                    'length' => $length,
                    'words' => $words,
                ]);
                unset($words);

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
     * Words of one length merged with their approved clues. Answers that only
     * exist as approved clues (not yet in the word list) are included with a
     * null score so no approved clue is left out of the export.
     *
     * @return list<array{word: string, score: float|null, clues: list<array{text: string, puzzle: array{title: string, author: string|null}|null}>}>
     */
    private function wordsForLength(int $length): array
    {
        $cluesByAnswer = $this->approvedCluesForLength($length);
        $words = [];

        foreach (Word::query()->toBase()->where('length', $length)->orderBy('word')->select(['word', 'score'])->cursor() as $row) {
            $words[] = [
                'word' => $row->word,
                'score' => round((float) $row->score, 2),
                'clues' => $cluesByAnswer[$row->word] ?? [],
            ];
            unset($cluesByAnswer[$row->word]);
        }

        foreach ($cluesByAnswer as $answer => $clues) {
            $words[] = ['word' => (string) $answer, 'score' => null, 'clues' => $clues];
        }

        usort($words, fn (array $a, array $b): int => strcmp($a['word'], $b['word']));

        return $words;
    }

    /**
     * Approved clues for answers of one length, grouped by answer in the order they were written.
     *
     * @return array<string, list<array{text: string, puzzle: array{title: string, author: string|null}|null}>>
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
            $cluesByAnswer[$row->answer][] = [
                'text' => $row->clue,
                'puzzle' => $row->puzzle_id === null ? null : [
                    'title' => $row->puzzle_title,
                    'author' => $row->puzzle_author,
                ],
            ];
        }

        return $cluesByAnswer;
    }

    private function approvedClues(): Builder
    {
        return DB::table('clue_entries')->where('clue_entries.status', ClueEntry::STATUS_APPROVED);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
