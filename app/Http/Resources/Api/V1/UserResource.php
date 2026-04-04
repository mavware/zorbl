<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class UserResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'users';
    }

    protected function resourceAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'copyright_name' => $this->copyright_name,
            'created_at' => $this->created_at,
        ];
    }

    protected function resourceMeta(Request $request): array
    {
        $meta = [];

        if ($this->crosswords_count !== null) {
            $meta['crosswords_count'] = $this->crosswords_count;
        }

        if ($this->followers_count !== null) {
            $meta['followers_count'] = $this->followers_count;
        }

        if ($this->following_count !== null) {
            $meta['following_count'] = $this->following_count;
        }

        return $meta;
    }
}
