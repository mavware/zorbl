<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $user->id === $webhookEndpoint->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $user->id === $webhookEndpoint->user_id;
    }

    public function delete(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $user->id === $webhookEndpoint->user_id;
    }
}
