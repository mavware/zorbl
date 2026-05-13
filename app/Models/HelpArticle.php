<?php

namespace App\Models;

use App\Observers\HelpArticleObserver;
use Database\Factories\HelpArticleFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use League\CommonMark\GithubFlavoredMarkdownConverter;

#[ObservedBy([HelpArticleObserver::class])]
class HelpArticle extends Model
{
    /** @use HasFactory<HelpArticleFactory> */
    use HasFactory;

    /**
     * Categories the storefront groups articles into. Keep keys stable; labels
     * are surfaced by displayCategory() so we can translate them.
     */
    public const CATEGORIES = [
        'getting-started' => 'Getting started',
        'constructing' => 'Constructing',
        'solving' => 'Solving',
        'account-billing' => 'Account & billing',
        'contests' => 'Contests',
    ];

    protected $fillable = [
        'category',
        'slug',
        'title',
        'summary',
        'body',
        'sort_order',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function getRenderedBodyAttribute(): string
    {
        return (string) (new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]))->convert($this->body);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('-', ' ', $this->category));
    }
}
