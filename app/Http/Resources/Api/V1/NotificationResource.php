<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'notifications';
    }

    protected function resourceAttributes(Request $request): array
    {
        return [
            'notification_type' => $this->data['type'] ?? null,
            'title' => $this->data['title'] ?? null,
            'body' => $this->data['body'] ?? null,
            'url' => $this->data['url'] ?? null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
