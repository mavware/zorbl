<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class ContestResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'contests';
    }

    protected function resourceAttributes(Request $request): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'rules' => $this->rules,
            'status' => $this->status,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'is_featured' => $this->is_featured,
            'max_meta_attempts' => $this->max_meta_attempts,
        ];
    }

    protected function resourceRelationships(Request $request): array
    {
        $relationships = [];

        if ($this->relationLoaded('user')) {
            $relationships['user'] = $this->relationshipReference('users', $this->user_id);
        }

        if ($this->relationLoaded('crosswords')) {
            $relationships['crosswords'] = [
                'data' => $this->crosswords->map(fn ($crossword) => [
                    'type' => 'crosswords',
                    'id' => (string) $crossword->getKey(),
                ]),
            ];
        }

        return $relationships;
    }

    protected function resourceMeta(Request $request): array
    {
        $meta = [];

        if ($this->entries_count !== null) {
            $meta['entries_count'] = $this->entries_count;
        }

        if ($this->crosswords_count !== null) {
            $meta['crosswords_count'] = $this->crosswords_count;
        }

        return $meta;
    }
}
