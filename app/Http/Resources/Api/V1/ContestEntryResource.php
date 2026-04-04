<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class ContestEntryResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'contest-entries';
    }

    protected function resourceAttributes(Request $request): array
    {
        return [
            'puzzles_completed' => $this->puzzles_completed,
            'total_solve_time_seconds' => $this->total_solve_time_seconds,
            'meta_solved' => $this->meta_solved,
            'rank' => $this->rank,
            'registered_at' => $this->registered_at,
        ];
    }

    protected function resourceRelationships(Request $request): array
    {
        $relationships = [];

        if ($this->relationLoaded('contest')) {
            $relationships['contest'] = $this->relationshipReference('contests', $this->contest_id);
        }

        if ($this->relationLoaded('user')) {
            $relationships['user'] = $this->relationshipReference('users', $this->user_id);
        }

        return $relationships;
    }

    protected function resourceMeta(Request $request): array
    {
        return [
            'formatted_solve_time' => $this->formattedSolveTime(),
        ];
    }
}
