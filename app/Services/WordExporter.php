<?php

namespace App\Services;

use App\Models\ClueEntry;
use App\Models\Word;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Writes the public word list (with scores and approved clues) to a storage
 * disk as static JSON, one shard per word length plus a manifest.
 *
 * Layout under the configured path:
 *   manifest.json        index of shards, counts, and a change fingerprint
 *   words/{NN}.json      every word of length NN with its score and clues
 *
 * The manifest's fingerprint lets a scheduled run skip rewriting when nothing
 * has changed, and lets clients skip shards whose hash they already hold.
 */
class WordExporter
{
    public const int VERSION = 1;

    public const string MANIFEST = 'manifest.json';

    /**
     * @return array{skipped: bool, shards: int, words: int, clues: int}
     */
    public function export(bool $force = false): array
    {
        $disk = $this->disk();
        $fingerprint = $this->fingerprint();
        $previous = $this->readManifest($disk);

        if (! $force && $previous !== null && ($previous['fingerprint'] ?? null) === $fingerprint) {
            return [
                'skipped' => true,
                'shards' => count($previous['shards'] ?? []),
                'words' => (int) ($previous['words'] ?? 0),
                'clues' => (int) ($previous['clues'] ?? 0),
            ];
        }

        $generatedAt = now()->toIso8601String();
        $cluesByLength = $this->approvedCluesByLength();
        $shards = [];
        $totalWords = 0;
        $totalClues = 0;

        foreach ($this->lengths($cluesByLength) as $length) {
            $words = $this->wordsForLength($length, $cluesByLength->get($length, collect()));
            $clueCount = $words->sum(fn (array $word): int => count($word['clues']));
            $path = $this->shardPath($length);
            $json = $this->encode([
                'version' => self::VERSION,
                'generated_at' => $generatedAt,
                'length' => $length,
                'words' => $words->values()->all(),
            ]);

            $disk->put($path, $json, 'public');

            $shards[] = [
                'length' => $length,
                'path' => $path,
                'words' => $words->count(),
                'clues' => $clueCount,
                'bytes' => strlen($json),
                'sha256' => hash('sha256', $json),
            ];
            $totalWords += $words->count();
            $totalClues += $clueCount;
        }

        $this->removeStaleShards($disk, $previous, $shards);

        $disk->put($this->fullPath(self::MANIFEST), $this->encode([
            'version' => self::VERSION,
            'generated_at' => $generatedAt,
            'fingerprint' => $fingerprint,
            'words' => $totalWords,
            'clues' => $totalClues,
            'shards' => $shards,
        ]), 'public');

        return [
            'skipped' => false,
            'shards' => count($shards),
            'words' => $totalWords,
            'clues' => $totalClues,
        ];
    }

    /**
     * Public URL of the manifest, for clients discovering the export.
     */
    public function manifestUrl(): string
    {
        return $this->disk()->url($this->fullPath(self::MANIFEST));
    }

    public function shardPath(int $length): string
    {
        return $this->fullPath(sprintf('words/%02d.json', $length));
    }

    /**
     * A cheap digest of everything the export depends on. Any word or approved
     * clue being added, removed, rescored, or re-reviewed changes it.
     */
    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            self::VERSION,
            Word::query()->count(),
            (string) Word::query()->max('updated_at'),
            ClueEntry::approved()->count(),
            (string) ClueEntry::approved()->max('updated_at'),
        ]));
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
     * @param  array<string, mixed>|null  $previous
     * @param  list<array{path: string}>  $current
     */
    private function removeStaleShards(Filesystem $disk, ?array $previous, array $current): void
    {
        $keep = array_column($current, 'path');

        foreach ($previous['shards'] ?? [] as $shard) {
            $path = $shard['path'] ?? null;
            if (is_string($path) && ! in_array($path, $keep, true)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifest(Filesystem $disk): ?array
    {
        $raw = $disk->get($this->fullPath(self::MANIFEST));
        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function fullPath(string $file): string
    {
        $base = (string) config('crosswordbuilder.word_export.path', 'exports/words');

        return $base === '' ? $file : "{$base}/{$file}";
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('crosswordbuilder.word_export.disk', 's3'));
    }
}
