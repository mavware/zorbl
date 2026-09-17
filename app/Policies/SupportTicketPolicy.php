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
        return $user->id === $ticket->user_id || $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return $user->id === $ticket->user_id || $user->hasRole('Admin');
    }

    public function respond(User $user, SupportTicket $ticket): bool
    {
        if ($ticket->status === 'closed') {
            return false;
        }

        return $user->id === $ticket->user_id || $user->hasRole('Admin');
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return $user->hasRole('Admin');
    }
}
