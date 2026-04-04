<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class PuzzleCommentResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'puzzle-comments';
    }

    protected function resourceAttributes(Request $request): array
    {
        return [
            'body' => $this->body,
            'rating' => $this->rating,
            'created_at' => $this->created_at,
        ];
    }

    protected function resourceRelationships(Request $request): array
    {
        $relationships = [];

        if ($this->relationLoaded('user')) {
            $relationships['user'] = $this->relationshipReference('users', $this->user_id);
        }

        if ($this->relationLoaded('crossword')) {
            $relationships['crossword'] = $this->relationshipReference('crosswords', $this->crossword_id);
        }

        return $relationships;
    }
}
