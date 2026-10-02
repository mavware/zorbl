<?php

namespace App\Services;

use App\Enums\TemplateStyle;
use App\Models\Template;
use App\Support\TemplateStats;

/**
 * Derives a first-pass set of style tags for a template from its structure.
 */
class TemplateTagger
{
    public function __construct(private TemplateStatsService $stats) {}

    /**
     * Tag a template that has no tags yet; manual tags are never overwritten.
     */
    public function tag(Template $template): void
    {
        if ($template->templateTags()->exists()) {
            return;
        }

        foreach ($this->deriveTags($template) as $tag) {
            $template->templateTags()->create(['tag' => $tag]);
        }
    }

    /**
     * @return list<TemplateStyle>
     */
    public function deriveTags(Template $template, ?TemplateStats $stats = null): array
    {
        $stats ??= $this->stats->forTemplate($template);
        $tags = [];
        $isMini = $template->width <= 7;
        $is15x15 = $template->width === 15 && $template->height === 15;

        if ($isMini) {
            $tags[] = TemplateStyle::MiniStyle;
        }

        $tags[] = $stats->isRotationallySymmetric
            ? TemplateStyle::RotationalSymmetric
            : TemplateStyle::Asymmetric;

        if ($stats->blockDensity <= 0.10) {
            $tags[] = TemplateStyle::WideOpen;
        } elseif ($stats->blockDensity >= 0.19) {
            $tags[] = TemplateStyle::Blocky;
        }

        if (! empty($template->styles)) {
            $tags[] = TemplateStyle::BarGrid;
        }

        if ($template->min_word_length < 3 || ! $stats->isFullyChecked) {
            $tags[] = TemplateStyle::RelaxedRules;
        }

        if ($is15x15) {
            $isThemeless = $stats->avgWordLength >= 5.5 || $stats->blockDensity <= 0.10;
            $tags[] = $isThemeless ? TemplateStyle::ThemelessFriendly : TemplateStyle::ThemedFriendly;
        }

        // TripleStack is only meaningful at full crossword width — three stacked
        // 5-letter rows in a mini aren't what constructors mean by "triple-stack".
        if ($template->width >= 13 && empty($template->styles) && $this->hasTripleStack($template->grid)) {
            $tags[] = TemplateStyle::TripleStack;
        }

        return $tags;
    }

    /**
     * Detect three or more consecutive rows with no blocks (stacked full-width entries).
     *
     * @param  array<int, array<int, int|string>>  $grid
     */
    private function hasTripleStack(array $grid): bool
    {
        $consecutive = 0;
        foreach ($grid as $row) {
            if (! in_array('#', $row, true)) {
                $consecutive++;
                if ($consecutive >= 3) {
                    return true;
                }
            } else {
                $consecutive = 0;
            }
        }

        return false;
    }
}
