<?php

use App\Models\HelpArticle;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private string $slug = 'import-existing-puzzles';

    private string $linkParagraph = "\n\nNot building a puzzle — just need to switch a file between formats? Use the free [crossword file converter](/tools/convert), no account required.";

    /**
     * Add a contextual internal link from the "import puzzles" help article to
     * the public format-converter page (a strong SEO landing page). Idempotent.
     */
    public function up(): void
    {
        $article = HelpArticle::where('slug', $this->slug)->first();

        if ($article === null || str_contains($article->body, '/tools/convert')) {
            return;
        }

        $article->body = rtrim($article->body).$this->linkParagraph;
        $article->save();
    }

    public function down(): void
    {
        $article = HelpArticle::where('slug', $this->slug)->first();

        if ($article === null) {
            return;
        }

        $article->body = str_replace($this->linkParagraph, '', $article->body);
        $article->save();
    }
};
