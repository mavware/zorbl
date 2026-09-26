<?php

namespace App\Observers;

use App\Http\Controllers\SitemapController;
use App\Models\ClueEntry;

class ClueEntryObserver
{
    public function saving(ClueEntry $clueEntry): void
    {
        if (! $clueEntry->exists || $clueEntry->isDirty(['clue', 'answer'])) {
            $clueEntry->refreshQualityIssues();
        }
    }

    public function saved(ClueEntry $clueEntry): void
    {
        $this->invalidateSitemapIfRelevant($clueEntry);
    }

    public function deleted(ClueEntry $clueEntry): void
    {
        $this->invalidateSitemapIfRelevant($clueEntry);
    }

    /**
     * Only approved clues make a word page indexable (and therefore listed in
     * the sitemap), so pending submissions and edits to them leave the cache
     * alone. Anything that moves a clue into or out of the approved set, or
     * re-points an approved clue at a different answer, rebuilds it.
     */
    private function invalidateSitemapIfRelevant(ClueEntry $clueEntry): void
    {
        $isApproved = $clueEntry->status === ClueEntry::STATUS_APPROVED;
        $wasApproved = $clueEntry->getOriginal('status') === ClueEntry::STATUS_APPROVED;

        if ($isApproved || $wasApproved) {
            SitemapController::invalidate([SitemapController::CACHE_KEY_WORDS]);
        }
    }
}
