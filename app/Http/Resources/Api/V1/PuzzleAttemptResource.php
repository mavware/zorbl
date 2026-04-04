<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class PuzzleAttemptResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'puzzle-attempts';
    }

    protected function resourceAttributes(Request $request): array
    {
        return [
            'progress' => $this->progress,
            'pencil_cells' => $this->pencil_cells,
            'is_completed' => $this->is_completed,
            'solve_time_seconds' => $this->solve_time_seconds,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
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

    protected function resourceMeta(Request $request): array
    {
        return [
            'solve_progress' => $this->solveProgress(),
            'formatted_solve_time' => $this->formattedSolveTime(),
        ];
    }
}
