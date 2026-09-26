<?php

namespace App\Services;

use App\Models\ClueEntry;
use App\Models\Word;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
        return Cache::get($this->cacheKey('manifest')) ?? $this->build()['manifest'];
    }

    /**
     * The JSON shard for one word length, or null when no word has that length.
     */
    public function shard(int $length): ?string
    {
        return Cache::get($this->cacheKey("shard:{$length}")) ?? ($this->build()['shards'][$length] ?? null);
    }

    /**
     * Build every shard and the manifest and store them in the cache.
     *
     * @return array{manifest: string, shards: array<int, string>, words: int, clues: int}
     */
    public function build(): array
    {
        $fingerprint = $this->fingerprint();

        return Cache::lock("word-export:build:{$fingerprint}", 120)->block(60, function () use ($fingerprint): array {
            $generatedAt = now()->toIso8601String();
            $cluesByLength = $this->approvedCluesByLength();
            $shards = [];
            $shardSummaries = [];
            $totalWords = 0;
            $totalClues = 0;

            foreach ($this->lengths($cluesByLength) as $length) {
                $words = $this->wordsForLength($length, $cluesByLength->get($length, collect()));
                $clueCount = $words->sum(fn (array $word): int => count($word['clues']));
                $json = $this->encode([
                    'version' => self::VERSION,
                    'generated_at' => $generatedAt,
                    'length' => $length,
                    'words' => $words->values()->all(),
                ]);

                $shards[$length] = $json;
                $shardSummaries[] = [
                    'length' => $length,
                    'url' => route('api.v1.words.shard', $length),
                    'words' => $words->count(),
                    'clues' => $clueCount,
                    'bytes' => strlen($json),
                    'sha256' => hash('sha256', $json),
                ];
                $totalWords += $words->count();
                $totalClues += $clueCount;
            }

            $manifest = $this->encode([
                'version' => self::VERSION,
                'generated_at' => $generatedAt,
                'fingerprint' => $fingerprint,
                'words' => $totalWords,
                'clues' => $totalClues,
                'shards' => $shardSummaries,
            ]);

            foreach ($shards as $length => $json) {
                Cache::put($this->cacheKey("shard:{$length}", $fingerprint), $json, self::EXPORT_SECONDS);
            }
            Cache::put($this->cacheKey('manifest', $fingerprint), $manifest, self::EXPORT_SECONDS);

            return [
                'manifest' => $manifest,
                'shards' => $shards,
                'words' => $totalWords,
                'clues' => $totalClues,
            ];
        });
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
     * @param  Collection<int, Collection<int, ClueEntry>>  $cluesByLength
     * @return list<int>
     */
    private function lengths(Collection $cluesByLength): array
    {
        $lengths = Word::query()->distinct()->pluck('length')
            ->merge($cluesByLength->keys())
            ->map(fn (int|string $length): int => (int) $length)
            ->filter(fn (int $length): bool => $length > 0)
            ->unique()
            ->sort()
            ->values();

        return $lengths->all();
    }

    /**
     * Words of one length merged with their approved clues. Answers that only
     * exist as approved clues (not yet in the word list) are included with a
     * null score so no approved clue is left out of the export.
     *
     * @param  Collection<int, ClueEntry>  $clues
     * @return Collection<int, array{word: string, score: float|null, clues: list<array{text: string, puzzle: array{title: string, author: string|null}|null}>}>
     */
    private function wordsForLength(int $length, Collection $clues): Collection
    {
        $cluesByAnswer = $clues->groupBy('answer');

        $words = Word::query()
            ->where('length', $length)
            ->orderBy('word')
            ->get(['word', 'score'])
            ->map(fn (Word $word): array => [
                'word' => $word->word,
                'score' => round((float) $word->score, 2),
                'clues' => $this->formatClues($cluesByAnswer->get($word->word, collect())),
            ]);

        $known = $words->pluck('word')->flip();

        $orphans = $cluesByAnswer
            ->reject(fn (Collection $entries, string $answer): bool => $known->has($answer))
            ->map(fn (Collection $entries, string $answer): array => [
                'word' => $answer,
                'score' => null,
                'clues' => $this->formatClues($entries),
            ])
            ->values();

        return $words->concat($orphans)->sortBy('word')->values();
    }

    /**
     * @param  Collection<int, ClueEntry>  $entries
     * @return list<array{text: string, puzzle: array{title: string, author: string|null}|null}>
     */
    private function formatClues(Collection $entries): array
    {
        return $entries
            ->sortBy('id')
            ->map(fn (ClueEntry $entry): array => [
                'text' => $entry->clue,
                'puzzle' => $entry->crossword === null ? null : [
                    'title' => $entry->crossword->title,
                    'author' => $entry->crossword->author,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Collection<int, ClueEntry>>
     */
    private function approvedCluesByLength(): Collection
    {
        return ClueEntry::approved()
            ->with('crossword:id,title,author')
            ->orderBy('id')
            ->get(['id', 'answer', 'clue', 'crossword_id'])
            ->groupBy(fn (ClueEntry $entry): int => mb_strlen($entry->answer));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
