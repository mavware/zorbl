<?php

namespace App\Services\PuzzleFeeds;

use App\Enums\PuzzlePublication;
use App\Support\PublishedPuzzle;
use Carbon\CarbonImmutable;
use CrosswordBuilder\CrosswordIO\GridNumberer;
use Illuminate\Support\Facades\Http;

/**
 * Newsday's daily crossword (edited by Stanley Newman), addressed by date.
 *
 * The feed is a plain-text document of blank-line-separated blocks: a status
 * word, the date as YYMMDD, "MM/DD/YY TITLE", the byline, width, height, the
 * across and down clue counts, the solution grid ("#" for black squares), then
 * the across clues and the down clues in numbered order. Asking for a date
 * that has no puzzle returns the latest puzzle instead, so the date block is
 * checked against the request.
 */
class NewsdayFeed implements PuzzleFeed
{
    public const string FEED_URL = 'https://brainsonly.com/servlets-newsday-crossword/newsdaycrossword';

    /**
     * A date this far in the past that still returns another puzzle has no
     * puzzle of its own and is skipped rather than waited for.
     */
    public const int MISSING_AFTER_DAYS = 3;

    public function __construct(private readonly GridNumberer $numberer) {}

    public function initialCursor(): string
    {
        return today()->subDay()->toDateString();
    }

    public function fetch(string $cursor): ?PublishedPuzzle
    {
        $date = CarbonImmutable::parse($cursor)->startOfDay();

        $body = Http::withUserAgent(config('app.name').'/1.0 ('.config('app.url').')')
            ->timeout(30)
            ->retry(3, 1000)
            ->get(self::FEED_URL, ['date' => $date->format('ymd')])
            ->throw()
            ->body();

        $blocks = self::blocks($body);

        if (($blocks[1] ?? null) !== $date->format('ymd')) {
            if ($date->lt(today()->subDays(self::MISSING_AFTER_DAYS))) {
                throw new UnreadablePuzzleException("Newsday has no puzzle for {$date->toDateString()}.");
            }

            return null;
        }

        return new PublishedPuzzle(
            PuzzlePublication::Newsday,
            $date,
            self::title($blocks[2] ?? ''),
            $this->entries($blocks, $date),
        );
    }

    public function nextCursor(string $cursor): string
    {
        return CarbonImmutable::parse($cursor)->addDay()->toDateString();
    }

    /**
     * @return list<string>
     */
    private static function blocks(string $body): array
    {
        $blocks = preg_split('/\R\s*\R/', trim(str_replace("\r", '', $body))) ?: [];

        return array_values(array_map(trim(...), $blocks));
    }

    private static function title(string $titleBlock): string
    {
        return trim((string) preg_replace('#^\d{1,2}/\d{1,2}/\d{2,4}\s*#', '', $titleBlock));
    }

    /**
     * Pair each numbered slot in the grid with the clue in the same position
     * of the across or down list.
     *
     * @param  list<string>  $blocks
     * @return list<array{answer: string, clue: string}>
     */
    private function entries(array $blocks, CarbonImmutable $date): array
    {
        $width = (int) ($blocks[4] ?? 0);
        $height = (int) ($blocks[5] ?? 0);
        $rows = explode("\n", $blocks[8] ?? '');
        $across = explode("\n", $blocks[9] ?? '');
        $down = explode("\n", $blocks[10] ?? '');

        if ($width < 1 || $height < 1 || count($rows) !== $height) {
            throw new UnreadablePuzzleException("Newsday puzzle for {$date->toDateString()} has an unreadable grid.");
        }

        $grid = array_map(fn (string $row): array => str_split(trim($row)), $rows);

        foreach ($grid as $row) {
            if (count($row) !== $width) {
                throw new UnreadablePuzzleException("Newsday puzzle for {$date->toDateString()} has an unreadable grid.");
            }
        }

        $slots = $this->numberer->number($grid, $width, $height);

        if (count($slots['across']) !== count($across) || count($slots['down']) !== count($down)) {
            throw new UnreadablePuzzleException("Newsday puzzle for {$date->toDateString()} has clues that don't match its grid.");
        }

        $entries = [];

        foreach (['across' => $across, 'down' => $down] as $direction => $clues) {
            foreach ($slots[$direction] as $index => $slot) {
                $entries[] = [
                    'answer' => self::answer($grid, $slot, $direction),
                    'clue' => trim($clues[$index]),
                ];
            }
        }

        return $entries;
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     * @param  array{row: int, col: int, length: int}  $slot
     */
    private static function answer(array $grid, array $slot, string $direction): string
    {
        $letters = '';

        for ($i = 0; $i < $slot['length']; $i++) {
            $letters .= $direction === 'across'
                ? $grid[$slot['row']][$slot['col'] + $i]
                : $grid[$slot['row'] + $i][$slot['col']];
        }

        return strtoupper($letters);
    }
}
