<?php

namespace App\Observers;

use App\Http\Controllers\SitemapController;
use App\Models\Crossword;
use App\Support\ProfanityFilter;
use Illuminate\Support\Facades\Cache;

class CrosswordObserver
{
    public function __construct(private readonly ProfanityFilter $filter) {}

    /**
     * Recompute the contains_profanity flag before each save so the column
     * stays in sync with title/clue edits without any extra controller code.
     */
    public function saving(Crossword $crossword): void
    {
        $clueText = array_merge(
            $this->collectClueText($crossword->clues_across ?? []),
            $this->collectClueText($crossword->clues_down ?? []),
        );

        $crossword->contains_profanity = $this->filter->contains((string) $crossword->title)
            || $this->filter->containsAny($clueText);
    }

    public function saved(Crossword $crossword): void
    {
        $this->invalidateSitemapIfRelevant($crossword);
    }

    public function deleted(Crossword $crossword): void
    {
        $this->invalidateSitemapIfRelevant($crossword);
    }

    /**
     * Clue arrays vary in shape (sometimes [number => clue], sometimes a list
     * of objects). Flatten to a plain list of strings for the filter.
     *
     * @param  array<int|string, mixed>  $clues
     * @return array<int, string>
     */
    private function collectClueText(array $clues): array
    {
        $out = [];
        foreach ($clues as $value) {
            if (is_string($value)) {
                $out[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $inner) {
                    if (is_string($inner)) {
                        $out[] = $inner;
                    }
                }
            }
        }

        return $out;
    }

    private function invalidateSitemapIfRelevant(Crossword $crossword): void
    {
        $isPublished = (bool) $crossword->is_published;
        $wasPublished = (bool) ($crossword->getOriginal('is_published') ?? false);

        if ($isPublished || $wasPublished) {
            Cache::forget(SitemapController::CACHE_KEY);
        }
    }
}
