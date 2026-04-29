<?php

namespace Database\Seeders;

use App\Enums\TemplateStyle;
use App\Models\Template;
use App\Services\TemplateStatsService;
use App\Support\TemplateStats;
use Illuminate\Database\Seeder;

class TemplateTagSeeder extends Seeder
{
    /**
     * Apply a first-pass set of TemplateStyle tags to each template based
     * on its computed stats. Skips templates that already have any tags so
     * subsequent manual edits aren't overwritten.
     */
    public function run(): void
    {
        $service = app(TemplateStatsService::class);

        Template::with('templateTags')->get()->each(function (Template $template) use ($service) {
            if ($template->templateTags->isNotEmpty()) {
                return;
            }

            $stats = $service->forTemplate($template);

            foreach ($this->deriveTags($template, $stats) as $tag) {
                $template->templateTags()->create(['tag' => $tag]);
            }
        });
    }

    /**
     * @return list<TemplateStyle>
     */
    private function deriveTags(Template $template, TemplateStats $stats): array
    {
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
