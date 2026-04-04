<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class MeResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'users';
    }

    protected function resourceAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'copyright_name' => $this->copyright_name,
            'current_streak' => $this->current_streak,
            'longest_streak' => $this->longest_streak,
            'last_solve_date' => $this->last_solve_date,
            'created_at' => $this->created_at,
        ];
    }
}
