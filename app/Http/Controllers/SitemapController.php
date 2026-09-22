<?php

namespace App\Http\Controllers;

use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\HelpArticle;
use App\Models\User;
use App\Models\Word;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public const CACHE_KEY_INDEX = 'sitemap.index';

    public const CACHE_KEY_PAGES = 'sitemap.pages';

    public const CACHE_KEY_PUZZLES = 'sitemap.puzzles';

    public const CACHE_KEY_CONSTRUCTORS = 'sitemap.constructors';

    public const CACHE_KEY_WORDS = 'sitemap.words';

    /** @deprecated Use the section-specific cache keys instead. */
    public const CACHE_KEY = 'sitemap.xml';

    /**
     * A word page needs this many approved clues before the sitemap promotes
     * it. Pages with one or two clues stay indexable (and reachable from the
     * clue library) but are too thin to spend Google's crawl budget on; the
     * sitemap should advertise the strongest pages, not the whole dictionary.
     */
    public const MIN_APPROVED_CLUES_FOR_WORDS = 3;

    /** @var array<string, string> */
    public const SECTIONS = [
        'pages' => self::CACHE_KEY_PAGES,
        'puzzles' => self::CACHE_KEY_PUZZLES,
        'constructors' => self::CACHE_KEY_CONSTRUCTORS,
        'words' => self::CACHE_KEY_WORDS,
    ];

    public function index(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY_INDEX, now()->addHour(), function (): string {
            return $this->buildIndex();
        });

        return $this->xmlResponse($xml);
    }

    public function section(string $section): Response
    {
        $cacheKey = self::SECTIONS[$section] ?? null;

        if ($cacheKey === null) {
            abort(404);
        }

        $xml = Cache::remember($cacheKey, now()->addHour(), function () use ($section): string {
            return match ($section) {
                'pages' => $this->buildPages(),
                'puzzles' => $this->buildPuzzles(),
                'constructors' => $this->buildConstructors(),
                'words' => $this->buildWords(),
            };
        });

        return $this->xmlResponse($xml);
    }

    /**
     * @param  array<int, string>  $keys
     */
    public static function invalidate(array $keys): void
    {
        Cache::forget(self::CACHE_KEY_INDEX);

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    private function buildIndex(): string
    {
        $sitemaps = '';

        foreach (array_keys(self::SECTIONS) as $section) {
            $sitemaps .= '<sitemap>'
                .'<loc>'.htmlspecialchars(route('sitemap.section', $section), ENT_XML1).'</loc>'
                .'</sitemap>'.PHP_EOL;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL
            .$sitemaps
            .'</sitemapindex>'.PHP_EOL;
    }

    private function buildPages(): string
    {
        $urls = [];

        $urls[] = $this->urlEntry(route('home'), now(), 'daily', '1.0');
        $urls[] = $this->urlEntry(route('puzzles.index'), now(), 'hourly', '0.9');
        $urls[] = $this->urlEntry(route('puzzles.daily-history'), now(), 'daily', '0.8');
        $urls[] = $this->urlEntry(route('constructors.index'), now(), 'daily', '0.7');
        $urls[] = $this->urlEntry(route('words.index'), now(), 'weekly', '0.7');
        $urls[] = $this->urlEntry(route('clues.index'), now(), 'daily', '0.7');
        $urls[] = $this->urlEntry(route('tools.convert'), null, 'monthly', '0.8');
        $urls[] = $this->urlEntry(route('help.index'), now(), 'weekly', '0.6');
        $urls[] = $this->urlEntry(route('legal.terms'), null, 'yearly', '0.2');
        $urls[] = $this->urlEntry(route('legal.privacy'), null, 'yearly', '0.2');
        $urls[] = $this->urlEntry(route('legal.cookies'), null, 'yearly', '0.2');
        $urls[] = $this->urlEntry(route('legal.dmca'), null, 'yearly', '0.2');

        foreach (HelpArticle::query()->published()->orderBy('category')->orderBy('sort_order')->get() as $article) {
            $urls[] = $this->urlEntry(
                route('help.show', $article),
                $article->updated_at,
                'monthly',
                '0.5',
            );
        }

        return $this->wrapUrlset($urls);
    }

    private function buildPuzzles(): string
    {
        $urls = [];

        Crossword::query()
            ->where('is_published', true)
            ->where('contains_profanity', false)
            ->select(['id', 'updated_at'])
            ->orderByDesc('updated_at')
            ->chunk(1000, function ($chunk) use (&$urls): void {
                foreach ($chunk as $crossword) {
                    $urls[] = $this->urlEntry(
                        route('puzzles.solve', $crossword->id),
                        $crossword->updated_at,
                        'weekly',
                        '0.7',
                    );
                }
            });

        return $this->wrapUrlset($urls);
    }

    private function buildConstructors(): string
    {
        $urls = [];

        User::query()
            ->where('is_anonymous', false)
            ->whereHas('crosswords', fn ($q) => $q->where('is_published', true)->where('contains_profanity', false))
            ->select(['id', 'updated_at'])
            ->orderByDesc('updated_at')
            ->chunk(1000, function ($chunk) use (&$urls): void {
                foreach ($chunk as $constructor) {
                    $urls[] = $this->urlEntry(
                        route('constructors.show', $constructor->id),
                        $constructor->updated_at,
                        'weekly',
                        '0.6',
                    );
                }
            });

        return $this->wrapUrlset($urls);
    }

    private function buildWords(): string
    {
        $urls = [];

        Word::query()
            ->whereHas(
                'clueEntries',
                fn (Builder $q) => $q->where('status', ClueEntry::STATUS_APPROVED),
                '>=',
                self::MIN_APPROVED_CLUES_FOR_WORDS,
            )
            ->select(['id', 'word'])
            ->addSelect([
                'last_clue_at' => ClueEntry::query()
                    ->approved()
                    ->whereColumn('clue_entries.answer', 'words.word')
                    ->selectRaw('max(updated_at)'),
            ])
            ->orderBy('word')
            ->chunk(1000, function ($chunk) use (&$urls): void {
                foreach ($chunk as $word) {
                    $lastClueAt = $word->getAttribute('last_clue_at');

                    $urls[] = $this->urlEntry(
                        route('words.show', $word),
                        $lastClueAt !== null ? CarbonImmutable::parse($lastClueAt) : null,
                        'weekly',
                        '0.5',
                    );
                }
            });

        return $this->wrapUrlset($urls);
    }

    /**
     * @param  array<int, string>  $urls
     */
    private function wrapUrlset(array $urls): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL
            .implode('', $urls)
            .'</urlset>'.PHP_EOL;
    }

    private function urlEntry(string $loc, ?\DateTimeInterface $lastmod, string $changefreq, string $priority): string
    {
        $entry = '<url>'
            .'<loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>';

        if ($lastmod !== null) {
            $entry .= '<lastmod>'.$lastmod->format('Y-m-d').'</lastmod>';
        }

        return $entry
            .'<changefreq>'.$changefreq.'</changefreq>'
            .'<priority>'.$priority.'</priority>'
            .'</url>'.PHP_EOL;
    }

    private function xmlResponse(string $xml): Response
    {
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
