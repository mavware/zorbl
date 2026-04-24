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
        if ($user->id === $ticket->user_id) {
            return true;
        }

        return $user->hasRole('Admin') && $ticket->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        if ($user->id === $ticket->user_id) {
            return true;
        }

        return $user->hasRole('Admin') && $ticket->assigned_to === $user->id;
    }

    public function respond(User $user, SupportTicket $ticket): bool
    {
        if ($ticket->status === 'closed') {
            return false;
        }

        if ($user->id === $ticket->user_id) {
            return true;
        }

        return $user->hasRole('Admin') && $ticket->assigned_to === $user->id;
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return $user->hasRole('Admin');
    }
}
