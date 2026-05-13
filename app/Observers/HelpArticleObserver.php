<?php

namespace App\Observers;

use App\Http\Controllers\SitemapController;
use App\Models\HelpArticle;
use Illuminate\Support\Facades\Cache;

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
            Cache::forget(SitemapController::CACHE_KEY);
        }
    }
}
