<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class FavoriteListResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'favorite-lists';
    }

    protected function resourceAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'created_at' => $this->created_at,
        ];
    }

    protected function resourceRelationships(Request $request): array
    {
        $relationships = [];

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

        if ($this->crosswords_count !== null) {
            $meta['crosswords_count'] = $this->crosswords_count;
        }

        return $meta;
    }
}
