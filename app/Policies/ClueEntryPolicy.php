<?php

namespace App\Policies;

use App\Models\ClueEntry;
use App\Models\User;

class ClueEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ClueEntry $clueEntry): bool
    {
        return $user->id === $clueEntry->user_id || $user->hasRole('Admin');
    }

    public function delete(User $user, ClueEntry $clueEntry): bool
    {
        return $user->id === $clueEntry->user_id || $user->hasRole('Admin');
    }
}
