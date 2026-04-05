<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ClueEntry;
use Illuminate\Http\Request;

/**
 * @mixin ClueEntry
 */
class ClueEntryResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'clue-entries';
    }

    protected function resourceAttributes(Request $request): array
    {
        return [
            'answer' => $this->answer,
            'clue' => $this->clue,
            'direction' => $this->direction,
            'clue_number' => $this->clue_number,
            'created_at' => $this->created_at,
        ];
    }

    protected function resourceRelationships(Request $request): array
    {
        $relationships = [];

        if ($this->relationLoaded('crossword')) {
            $relationships['crossword'] = $this->relationshipReference('crosswords', $this->crossword_id);
        }

        if ($this->relationLoaded('user')) {
            $relationships['user'] = $this->relationshipReference('users', $this->user_id);
        }

        return $relationships;
    }
}
