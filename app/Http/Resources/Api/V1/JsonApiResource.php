<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base JSON:API resource.
 *
 * Wraps Laravel's JsonResource to output the JSON:API envelope:
 * { "data": { "type": "...", "id": "...", "attributes": {...}, "relationships": {...}, "links": {...} } }
 */
abstract class JsonApiResource extends JsonResource
{
    /**
     * The JSON:API resource type (e.g. "crosswords", "users").
     */
    abstract protected function resourceType(): string;

    /**
     * The attributes to include in the response.
     *
     * @return array<string, mixed>
     */
    abstract protected function resourceAttributes(Request $request): array;

    /**
     * Optional relationships to include.
     *
     * @return array<string, mixed>
     */
    protected function resourceRelationships(Request $request): array
    {
        return [];
    }

    /**
     * Optional meta data.
     *
     * @return array<string, mixed>
     */
    protected function resourceMeta(Request $request): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'type' => $this->resourceType(),
            'id' => (string) $this->resource->getKey(),
            'attributes' => $this->resourceAttributes($request),
        ];

        $relationships = $this->resourceRelationships($request);
        if (! empty($relationships)) {
            $data['relationships'] = $relationships;
        }

        $meta = $this->resourceMeta($request);
        if (! empty($meta)) {
            $data['meta'] = $meta;
        }

        return $data;
    }

    /**
     * Format a relationship reference for JSON:API.
     *
     * @return array{data: array{type: string, id: string}}
     */
    protected function relationshipReference(string $type, string|int $id): array
    {
        return [
            'data' => [
                'type' => $type,
                'id' => (string) $id,
            ],
        ];
    }
}
