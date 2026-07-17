<?php

namespace App\Http\Controllers;

use App\Models\Crossword;
use App\Models\HelpArticle;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public const CACHE_KEY = 'sitemap.xml';

    /**
     * Serve sitemap.xml for crawler discovery. We list every public URL and
     * every published puzzle. Cached for an hour to avoid touching the DB on
     * every Googlebot fetch; the cache is also invalidated immediately when
     * a puzzle is published, updated, or deleted (see CrosswordObserver).
     */
    public function index(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, now()->addHour(), function (): string {
            return $this->build();
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function build(): string
    {
        $urls = [];

        // Top-level public pages. `priority` and `changefreq` are hints; Google
        // mostly ignores them, but the structure is required by the protocol.
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

        // Public constructor profiles: real accounts with at least one publicly
        // visible published puzzle.
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
}
