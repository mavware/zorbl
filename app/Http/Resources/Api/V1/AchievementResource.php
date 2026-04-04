<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class AchievementResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'achievements';
    }

    protected function resourceAttributes(Request $request): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'earned_at' => $this->earned_at,
        ];
    }
}
