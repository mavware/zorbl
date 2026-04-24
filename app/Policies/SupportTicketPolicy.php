<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $user->id === $ticket->user_id
            || $user->id === $ticket->assigned_to;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function respond(User $user, SupportTicket $ticket): bool
    {
        if ($ticket->status === 'closed') {
            return false;
        }

        return $user->id === $ticket->user_id
            || $user->id === $ticket->assigned_to;
    }
}
