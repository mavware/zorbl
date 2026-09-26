<?php

namespace App\Services;

use App\Console\Commands\GenerateWordList;
use App\Models\Word;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Adds words and multi-word phrases from Wiktionary (CC BY-SA) that aren't in
 * the word list yet.
 *
 * Walks a Wiktionary category 500 entries per API page. The position is saved
 * after every page so each run continues where the last one stopped; after the
 * last page the walk starts over, which picks up entries added since.
 */
class WiktionaryWordImporter
{
    public const string API_URL = 'https://en.wiktionary.org/w/api.php';

    public const string DEFAULT_CATEGORY = 'English lemmas';

    public const int MIN_WORD_LENGTH = 3;

    /**
     * The longest entry in the current word list.
     */
    public const int MAX_WORD_LENGTH = 21;

    /**
     * Longer phrases are rarely usable fill.
     */
    public const int MAX_PHRASE_WORDS = 3;

    /**
     * Imported entries are unvetted, so they score below words from published
     * puzzles and the original list and autofill prefers those.
     */
    public const float SCORE_PENALTY = 10.0;

    /**
     * @return array{pages: int, scanned: int, added: int, finished: bool}
     *
     * @throws ConnectionException|RequestException when Wiktionary can't be reached.
     */
    public function import(int $pages, string $category = self::DEFAULT_CATEGORY): array
    {
        $cursorKey = $this->cursorKey($category);
        $scanned = 0;
        $added = 0;
        $fetched = 0;
        $finished = false;

        while ($fetched < $pages) {
            $response = $this->fetchPage($category, Cache::get($cursorKey));
            $fetched++;

            $titles = array_column($response['query']['categorymembers'] ?? [], 'title');
            $scanned += count($titles);
            $added += $this->addNewWords($titles);

            $next = $response['continue']['cmcontinue'] ?? null;

            if ($next === null) {
                Cache::forget($cursorKey);
                $finished = true;

                break;
            }

            Cache::forever($cursorKey, $next);
        }

        return ['pages' => $fetched, 'scanned' => $scanned, 'added' => $added, 'finished' => $finished];
    }

    /**
     * Forget the saved position so the next run starts from the beginning of the category.
     */
    public function restart(string $category = self::DEFAULT_CATEGORY): void
    {
        Cache::forget($this->cursorKey($category));
    }

    /**
     * Turn a Wiktionary entry title into a grid answer, or null when it isn't
     * usable: affixes ("-ness"), proper nouns ("Paris", which Wiktionary
     * capitalizes), acronyms ("NASA"), entries with digits or symbols, phrases
     * of more than three words, and anything outside the allowed length.
     * Accents are folded to
     * plain letters, and spaces, hyphens, and apostrophes are dropped, so
     * "hot dog" becomes HOTDOG.
     */
    public static function normalize(string $title): ?string
    {
        if (str_starts_with($title, '-') || str_ends_with($title, '-')) {
            return null;
        }

        $ascii = Str::ascii($title);

        if (! preg_match("/^[a-z][A-Za-z '\\-]*$/", $ascii)) {
            return null;
        }

        if (count(preg_split('/[ \\-]+/', $ascii)) > self::MAX_PHRASE_WORDS) {
            return null;
        }

        $letters = preg_replace('/[^A-Za-z]/', '', $ascii);

        if (strlen($letters) > 1 && $letters === strtoupper($letters)) {
            return null;
        }

        $length = strlen($letters);

        if ($length < self::MIN_WORD_LENGTH || $length > self::MAX_WORD_LENGTH) {
            return null;
        }

        return strtoupper($letters);
    }

    /**
     * @param  list<string>  $titles
     */
    private function addNewWords(array $titles): int
    {
        $candidates = collect($titles)
            ->map(fn (string $title): ?string => self::normalize($title))
            ->filter()
            ->unique()
            ->values();

        if ($candidates->isEmpty()) {
            return 0;
        }

        $existing = Word::query()->whereIn('word', $candidates->all())->pluck('word')->flip();
        $now = now();

        $rows = $candidates
            ->reject(fn (string $word): bool => $existing->has($word))
            ->map(fn (string $word): array => [
                'word' => $word,
                'length' => strlen($word),
                'score' => max(0.0, round(GenerateWordList::calculateScore($word) - self::SCORE_PENALTY, 2)),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        return $rows === [] ? 0 : Word::insertOrIgnore($rows);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException|RequestException
     */
    private function fetchPage(string $category, ?string $continue): array
    {
        return Http::withUserAgent(config('app.name').'/1.0 ('.config('app.url').')')
            ->timeout(30)
            ->retry(3, 1000)
            ->get(self::API_URL, array_filter([
                'action' => 'query',
                'list' => 'categorymembers',
                'cmtitle' => 'Category:'.$category,
                'cmnamespace' => 0,
                'cmlimit' => 500,
                'cmcontinue' => $continue,
                'format' => 'json',
                'formatversion' => 2,
            ], fn (mixed $value): bool => $value !== null))
            ->throw()
            ->json();
    }

    private function cursorKey(string $category): string
    {
        return 'wiktionary-import:cursor:'.Str::slug($category);
    }
}
