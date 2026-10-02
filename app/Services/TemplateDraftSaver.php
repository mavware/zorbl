<?php

namespace App\Services;

use App\Models\Template;
use App\Support\GenerationCandidate;

class TemplateDraftSaver
{
    public function __construct(private TemplateTagger $tagger) {}

    /**
     * Save valid candidates as inactive, tagged templates for admin review.
     *
     * @param  list<GenerationCandidate>  $candidates
     * @return list<int> template IDs that were saved
     */
    public function saveAsDrafts(array $candidates): array
    {
        $ids = [];
        foreach ($candidates as $candidate) {
            if (! $candidate->isValid()) {
                continue;
            }

            $template = Template::create([
                'name' => $this->uniqueName($candidate->name),
                'width' => $candidate->width,
                'height' => $candidate->height,
                'grid' => $candidate->grid,
                'styles' => null,
                'min_word_length' => 3,
                'sort_order' => 0,
                'is_active' => false,
            ]);

            $template->annotation()->create([
                'philosophy' => $candidate->philosophy,
                'strengths' => $candidate->strengths,
                'compromises' => $candidate->compromises,
                'best_for' => $candidate->bestFor,
                'avoid_when' => $candidate->avoidWhen,
            ]);

            $this->tagger->tag($template);

            $ids[] = $template->id;
        }

        return $ids;
    }

    private function uniqueName(string $proposed): string
    {
        $base = trim($proposed) !== '' ? trim($proposed) : 'Generated';
        $candidate = $base;
        $i = 2;

        while (Template::where('name', $candidate)->exists()) {
            $candidate = $base.' '.$i;
            $i++;
        }

        return $candidate;
    }
}
