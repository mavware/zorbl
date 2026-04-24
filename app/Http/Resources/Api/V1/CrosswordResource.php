<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Crossword;
use Illuminate\Http\Request;

/**
 * @mixin Crossword
 */
class CrosswordResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'crosswords';
    }

    protected function resourceAttributes(Request $request): array
    {
        $attributes = [
            'title' => $this->title,
            'author' => $this->author,
            'width' => $this->width,
            'height' => $this->height,
            'kind' => $this->kind,
            'difficulty_score' => $this->difficulty_score,
            'difficulty_label' => $this->difficulty_label,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($request->routeIs('api.v1.crosswords.show')) {
            $attributes['grid'] = $this->grid;
            $attributes['clues_across'] = $this->clues_across;
            $attributes['clues_down'] = $this->clues_down;
            $attributes['styles'] = $this->styles;
            $attributes['prefilled'] = $this->prefilled;
        }

        return $attributes;
    }

    protected function resourceRelationships(Request $request): array
    {
        $relationships = [];

        if ($this->relationLoaded('user')) {
            $relationships['user'] = $this->relationshipReference('users', $this->user_id);
        }

        if ($this->relationLoaded('tags')) {
            $relationships['tags'] = $this->tags->map(fn ($tag) => [
                'type' => 'tags',
                'id' => (string) $tag->id,
                'attributes' => [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ],
            ])->values()->all();
        }

        return $relationships;
    }

    protected function resourceMeta(Request $request): array
    {
        $meta = [];

        if ($this->likes_count !== null) {
            $meta['likes_count'] = $this->likes_count;
        }

        if ($this->attempts_count !== null) {
            $meta['attempts_count'] = $this->attempts_count;
        }

        if ($this->comments_count !== null) {
            $meta['comments_count'] = $this->comments_count;
        }

        return $meta;
    }
}
