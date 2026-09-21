<?php

namespace App\Observers;

use App\Http\Controllers\SitemapController;
use App\Models\HelpArticle;

class HelpArticleObserver
{
    public function saved(HelpArticle $article): void
    {
        $this->invalidateSitemapIfRelevant($article);
    }

    public function deleted(HelpArticle $article): void
    {
        $this->invalidateSitemapIfRelevant($article);
    }

    private function invalidateSitemapIfRelevant(HelpArticle $article): void
    {
        $isPublished = (bool) $article->is_published;
        $wasPublished = (bool) ($article->getOriginal('is_published') ?? false);

        if ($isPublished || $wasPublished) {
            SitemapController::invalidate([SitemapController::CACHE_KEY_PAGES]);
        }
    }
}
